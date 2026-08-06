#!/bin/bash
set -euo pipefail

# Topics consumed by any pipeline's `kafka` driver in `.rr/rr-kitchen.yaml`, across
# every bounded context, must be provisioned here — auto-create on the
# broker (KAFKA_AUTO_CREATE_TOPICS_ENABLE) does not reliably fire for
# consumer-only metadata requests, so RoadRunner's kgo driver can end up
# stuck retrying UNKNOWN_TOPIC_OR_PARTITION forever without this.
TOPICS=(
  "resource-management.menu"
  "resource-management.chef"
)

for topic in "${TOPICS[@]}"; do
  /opt/kafka/bin/kafka-topics.sh --bootstrap-server kafka:9092 \
    --create --if-not-exists \
    --topic "$topic" \
    --partitions 1 \
    --replication-factor 1
done
