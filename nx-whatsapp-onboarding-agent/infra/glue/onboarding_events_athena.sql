CREATE EXTERNAL TABLE IF NOT EXISTS nxtutors_onboarding_events (
  event_id BIGINT,
  conversation_id_hash STRING,
  role STRING,
  state STRING,
  event_type STRING,
  error_code STRING,
  latency_ms BIGINT,
  created_at STRING,
  channel STRING
)
PARTITIONED BY (
  app_version STRING,
  flow_version STRING
)
STORED AS PARQUET
LOCATION 's3://YOUR_BUCKET/nxtutors/onboarding_events/';
