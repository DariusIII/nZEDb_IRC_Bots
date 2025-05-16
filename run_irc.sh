#!/bin/bash
	# run_irc.sh - Manages nZEDb IRC scraper bots with automatic restart

	# Configuration - Edit these values as needed
	MAIN_PATH="/path/to/nzedb_pre_irc_bots/php"
	PHP_PATH="/usr/bin/php"
	LOG_FILE="/var/log/nzedb_bots.log"
	LOCK_FILE="/tmp/nzedb_bots.lock"
	CHECK_INTERVAL=300  # Seconds between checks

	# Bot configuration
	declare -A BOTS=(
	  ["serverbot"]="postIRC.php"
	  ["efnetbot"]="scrapeIRC.php efnet"
	  ["corruptbot"]="scrapeIRC.php corrupt"
	  ["webbot"]="scrapeWEB.php"
	  ["m2vrubot"]="scrapeM2VRU.php"
	  ["reqbot"]="scrapeREQ.php"
	)

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

	# Function to check if a bot is running
	is_bot_running() {
	  screen -list | grep -q "\.$1\s"
	}

	# Function to start a bot
	start_bot() {
	  local name=$1
	  local script=$2

	  if ! is_bot_running "$name"; then
	    log_message "Starting $name"
	    screen -dmS "$name" "$PHP_PATH" "${MAIN_PATH}/${script}"
	    sleep 2

	    if is_bot_running "$name"; then
	      log_message "$name started successfully"
	    else
	      log_message "ERROR: Failed to start $name"
	    fi
	  fi
	}

	# Function to check and restart all bots
	check_bots() {
	  for bot_name in "${!BOTS[@]}"; do
	    if ! is_bot_running "$bot_name"; then
	      log_message "$bot_name is not running - restarting"
	      start_bot "$bot_name" "${BOTS[$bot_name]}"
	      sleep 1
	    fi
	  done
	}

	# Function to handle shutdown
	cleanup() {
	  log_message "Shutting down all bots"
	  for bot_name in "${!BOTS[@]}"; do
	    if is_bot_running "$bot_name"; then
	      log_message "Stopping $bot_name"
	      screen -S "$bot_name" -X quit >/dev/null 2>&1
	    fi
	  done

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
	  check_bots
	  sleep "$CHECK_INTERVAL"
	done
