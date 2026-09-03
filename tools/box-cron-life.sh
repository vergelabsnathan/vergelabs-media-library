# How long did the cron requests live around the last run, and what kills them? Read-only.
set -e
echo "=== php-fpm limits"
grep -RhE "^\s*(request_terminate_timeout|pm\.max_children|pm\s*=|memory_limit|max_execution_time)" /etc/php/*/fpm/pool.d/*.conf /etc/php/*/fpm/php.ini 2>/dev/null | sed 's/^/  /'
echo "=== php-fpm log, last 20 lines"
tail -20 /var/log/php*-fpm.log 2>/dev/null | sed 's/^/  /'
echo "=== nginx access: wp-cron.php requests, last 40"
grep "wp-cron.php" /var/log/nginx/access.log | tail -40 | awk '{print "  "$4, $6, $7, $9, "bytes="$10, "t="$NF}'
echo "=== nginx log_format"
grep -hE "log_format" /etc/nginx/nginx.conf /etc/nginx/conf.d/*.conf 2>/dev/null | sed 's/^/  /' | head -3
echo "=== wp-cron processes alive now"
ps -eo pid,etimes,rss,args | grep -E "php-fpm: pool" | head -5 | sed 's/^/  /'
