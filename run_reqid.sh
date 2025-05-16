#!/bin/bash
# run_reqid.sh - Manages nZEDb Request ID scraper bot with automatic restart

# Configuration - Edit these values as needed
MAIN_PATH="/path/to/nzedb_pre_irc_bots/php"
PHP_PATH="/usr/bin/php"
LOG_FILE="/var/log/nzedb_reqid_bot.log"
LOCK_FILE="/tmp/nzedb_reqid_bot.lock"
CHECK_INTERVAL=300  # Seconds between checks
BOT_NAME="reqidbot"
SCRIPT_NAME="scrapeREQ.php"

# Ensure we have screen installed
if ! command -v screen >/dev/null 2>&1; then
  echo "Error: screen is not installed" >&2
  exit 1
fi

# Create log directory if needed
log_dir=$(dirname "$LOG_FILE")
mkdir -p "$log_dir" 2>/dev/null || {
  echo "Error: Cannot create log directory $log_dir" >&2
  exit 1
}

# Function to log messages
log_message() {
  local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  echo "[${timestamp}] $1" | tee -a "$LOG_FILE"
}

# Function to check if the bot is running
is_bot_running() {
  screen -list | grep -q "\.$BOT_NAME\s"
}

# Function to start the bot
start_bot() {
  if ! is_bot_running; then
    log_message "Starting $BOT_NAME"
    screen -dmS "$BOT_NAME" "$PHP_PATH" "${MAIN_PATH}/${SCRIPT_NAME}"
    sleep 2

    if is_bot_running; then
      log_message "$BOT_NAME started successfully"
    else
      log_message "ERROR: Failed to start $BOT_NAME"
    fi
  fi
}

# Function to handle shutdown
cleanup() {
  log_message "Shutting down bot"
  if is_bot_running; then
    log_message "Stopping $BOT_NAME"
    screen -S "$BOT_NAME" -X quit >/dev/null 2>&1
  fi

  rm -f "$LOCK_FILE"
  log_message "Shutdown complete"
  exit 0
}

# Setup signal handlers
trap cleanup SIGINT SIGTERM

# Check if script is already running
if [ -f "$LOCK_FILE" ]; then
  pid=$(cat "$LOCK_FILE")
  if kill -0 "$pid" >/dev/null 2>&1; then
    echo "Error: Script is already running with PID $pid" >&2
    exit 1
  else
    # Stale lock file
    rm -f "$LOCK_FILE"
  fi
fi

# Create lock file
echo $$ > "$LOCK_FILE"

# Main loop
log_message "Bot manager started"

while true; do
  if ! is_bot_running; then
    log_message "$BOT_NAME is not running - restarting"
    start_bot
  fi
  sleep "$CHECK_INTERVAL"
done
