#!/bin/sh
# collector.sh <アプリディレクトリ>
#
# OS5 の Web サーバーは www-data 権限で動き、/dev/sda を直接読めない。
# そのため root で動くこのコレクタが smartctl を実行し、
# data/<デバイス名>.json (または .txt) に書き出す。PHP 側はそれを読むだけ。
#
# - 起動直後に 1 回収集
# - 以後 5 秒ごとに data/.refresh を確認(Web の「再取得」で作られる)
# - 10 分おきに自動更新

APP_DIR="$1"
DATA_DIR="${APP_DIR}/data"
INTERVAL=600
SLEEP=5
LOG="${DATA_DIR}/collector.log"

mkdir -p "$DATA_DIR" 2>/dev/null

say() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"
}

smartctl_path() {
    if [ -s "${DATA_DIR}/smartctl.path" ]; then
        cat "${DATA_DIR}/smartctl.path"
        return 0
    fi
    for p in /usr/sbin/smartctl /usr/local/sbin/smartctl /usr/bin/smartctl /sbin/smartctl /opt/bin/smartctl; do
        if [ -x "$p" ]; then echo "$p"; return 0; fi
    done
    command -v smartctl 2>/dev/null
}

collect() {
    SC=$(smartctl_path)
    if [ -z "$SC" ]; then
        say "smartctl not found"
        return 1
    fi
    found=0
    for dev in /dev/sda /dev/sdb /dev/sdc /dev/sdd; do
        [ -b "$dev" ] || continue
        found=1
        base=$(basename "$dev")
        if "$SC" -a -j "$dev" > "${DATA_DIR}/.${base}.json.tmp" 2>/dev/null && [ -s "${DATA_DIR}/.${base}.json.tmp" ]; then
            mv -f "${DATA_DIR}/.${base}.json.tmp" "${DATA_DIR}/${base}.json"
            rm -f "${DATA_DIR}/${base}.txt"
        else
            rm -f "${DATA_DIR}/.${base}.json.tmp"
            if "$SC" -a "$dev" > "${DATA_DIR}/.${base}.txt.tmp" 2>/dev/null && [ -s "${DATA_DIR}/.${base}.txt.tmp" ]; then
                mv -f "${DATA_DIR}/.${base}.txt.tmp" "${DATA_DIR}/${base}.txt"
                rm -f "${DATA_DIR}/${base}.json"
            else
                rm -f "${DATA_DIR}/.${base}.txt.tmp"
                say "collect failed: $dev"
            fi
        fi
    done
    [ "$found" -eq 0 ] && say "no block devices found"
    date +%s > "${DATA_DIR}/updated"
    return 0
}

say "collector started (pid $$), app=${APP_DIR}"
collect

while :; do
    sleep "$SLEEP"
    if [ -f "${DATA_DIR}/.refresh" ]; then
        rm -f "${DATA_DIR}/.refresh"
        say "refresh requested"
        collect
    fi
    now=$(date +%s)
    upd=$(cat "${DATA_DIR}/updated" 2>/dev/null)
    [ -z "$upd" ] && upd=0
    if [ $((now - upd)) -ge "$INTERVAL" ]; then
        collect
    fi
done
