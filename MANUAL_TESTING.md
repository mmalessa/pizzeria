# Manual end-to-end testing

## System prerequisites

- `docker compose up -d` — Kafka topics auto-created by `kafka-init`.
- Exactly one `rr serve` process:
  `docker compose exec php sh -lc 'ps -ef | grep "rr serve"'`.
  `.rr/rr-kitchen.yaml` pins `num_workers: 1` per pool so in-memory repos stay one
  shared instance — only true for a single process. Two processes = two
  isolated states (e.g. a chef marked Active in one is invisible to the
  other).
- Message files are one line, not pretty-printed. Multi-line payloads get
  split line-by-line by `rabbitmqadmin`/`kafka-console-producer.sh` into
  malformed fragments (`JsonException: Syntax error`).
- Watch results: `docker compose exec php tail -f var/log/kitchen.log`.

## Kitchen

**What this tests:** a chef gets hired, then a guest orders 2 margheritas.
Kitchen turns that into two pizzas to make. The chef picks one up, finishes
it, picks up the second, finishes that too — and once both are done, Kitchen
marks the whole order ready for pickup. Each step below sends one message
that plays one of these moments by hand.

**Flow:**

### 1. Chef `chef-001` gets hired

Kafka message key = `chefId` (see "Message keys" below) — sent as
`key<TAB>value` on stdin, so the key isn't baked into `chef-hired.json`
itself:

```sh
printf 'chef-001\t%s\n' "$(cat .kafka/chef-hired.json)" | \
  docker compose exec -T kafka /opt/kafka/bin/kafka-console-producer.sh \
  --bootstrap-server kafka:9092 --topic resource-management.chef \
  --reader-property parse.key=true
```

**Check:** `docker compose exec php tail -n5 var/log/kitchen.log` shows
`[ActiveChefPool] hired/active chefId chef-001`.

### 2. A guest orders 2 margheritas

```sh
docker compose exec rabbitmq rabbitmqadmin -u demo -p demo publish message \
  --exchange=pizzeria --routing-key=kitchen-orders \
  --payload="$(cat .amqp/01-order-sent-to-kitchen.json)"
```

**Check:** same log file shows `[AcceptOrder] created pizzaTasks <id1>,<id2>
for kitchenOrderId ...` — copy those two `pizzaTaskId`s, you need them in
steps 4 and 5.

### 3. The chef picks up the first pizza

```sh
docker compose exec rabbitmq rabbitmqadmin -u demo -p demo publish message \
  --exchange=pizzeria --routing-key=kitchen-orders \
  --payload="$(cat .amqp/02-pick-up-pizza-from-queue.json)"
```

**Check:** success is silent — no new log line. If it failed, the log shows
`DomainException: Chef chef-001 is not Active` (chef-hired wasn't sent/
processed yet) or a similar guard failure.

### 4. The chef finishes it

Substitute `<pizzaTaskId1>` with the first id from step 2 (leaves the file
untouched, so you can reuse it for step 5 with the second id):

```sh
docker compose exec rabbitmq rabbitmqadmin -u demo -p demo publish message \
  --exchange=pizzeria --routing-key=kitchen-orders \
  --payload="$(sed 's/REPLACE_ME/<pizzaTaskId1>/' .amqp/03-finish-pizza.json)"
```

**Check:** log shows `[outbox] publish pizzeria.kitchen.chef-finished-pizza
...`.

### 5. Repeat for the second pizza

```sh
docker compose exec rabbitmq rabbitmqadmin -u demo -p demo publish message \
  --exchange=pizzeria --routing-key=kitchen-orders \
  --payload="$(cat .amqp/02-pick-up-pizza-from-queue.json)"

docker compose exec rabbitmq rabbitmqadmin -u demo -p demo publish message \
  --exchange=pizzeria --routing-key=kitchen-orders \
  --payload="$(sed 's/REPLACE_ME/<pizzaTaskId2>/' .amqp/03-finish-pizza.json)"
```

**Check:** log shows a second `chef-finished-pizza`, plus
`[outbox] publish pizzeria.kitchen.order-ready-for-pickup ...` — both
pizzas are done, the guest's order is ready.

**Message keys:** every Kafka message is keyed by the id of the aggregate
it's about (`chefId` for `resource-management.chef`, `menuItemId` for
`resource-management.menu`) — see `doc/09_architecture.md` §5. This keeps
every event for one aggregate in the same partition, so a consumer never
sees them out of order. AMQP messages have no such requirement (routed by
routing key, not partitioned), so `.amqp/*.json` files carry no key.
