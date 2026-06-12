import sys
from awsglue.context import GlueContext
from awsglue.job import Job
from awsglue.utils import getResolvedOptions
from pyspark.context import SparkContext

args = getResolvedOptions(sys.argv, ["JOB_NAME", "SOURCE_PATH", "TARGET_PATH"])
glue_context = GlueContext(SparkContext.getOrCreate())
spark = glue_context.spark_session
job = Job(glue_context)
job.init(args["JOB_NAME"], args)

events = spark.read.json(args["SOURCE_PATH"])
sanitized = events.select(
    "event_id",
    "conversation_id_hash",
    "role",
    "state",
    "event_type",
    "error_code",
    "latency_ms",
    "created_at",
    "app_version",
    "flow_version",
    "channel",
)
sanitized.write.mode("append").partitionBy("app_version", "flow_version").parquet(args["TARGET_PATH"])
job.commit()
