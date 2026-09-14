-- Persisted rate-limit buckets (login / forgot-password / pay). Wave 1.

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
  bucket_key   VARCHAR(191) NOT NULL PRIMARY KEY,
  window_start INT UNSIGNED NOT NULL,
  hit_count    INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
