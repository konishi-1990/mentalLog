-- テスト用データベースを初回起動時に作成（新規ボリューム時のみ実行される）
SELECT 'CREATE DATABASE mentallog_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'mentallog_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE mentallog_test TO mentallog;
