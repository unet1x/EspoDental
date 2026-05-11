# Alert helpers — Telegram + email via curl-SMTP.
# Sourced together with common.sh.
#
# Required env (any subset; channels with missing config are skipped):
#   ALERT_TELEGRAM_BOT_TOKEN, ALERT_TELEGRAM_CHAT_ID
#   ALERT_EMAIL_TO, ALERT_EMAIL_FROM, ALERT_SMTP_URL, ALERT_SMTP_USER, ALERT_SMTP_PASS

alert_send() {
    local subject="$1"
    local body="$2"
    local sent_any=0

    if alert_telegram "${subject}" "${body}"; then
        sent_any=1
    fi
    if alert_email "${subject}" "${body}"; then
        sent_any=1
    fi

    if [ "${sent_any}" -eq 0 ]; then
        log_warn "alert_send: no channel configured, alert is logged only"
    fi
}

alert_telegram() {
    local subject="$1"
    local body="$2"
    if [ -z "${ALERT_TELEGRAM_BOT_TOKEN:-}" ] || [ -z "${ALERT_TELEGRAM_CHAT_ID:-}" ]; then
        return 1
    fi
    local text
    text="$(printf '[EspoDental] %s\n\n%s' "${subject}" "${body}")"
    local resp
    resp="$(curl -sS \
        --max-time 20 \
        -X POST "https://api.telegram.org/bot${ALERT_TELEGRAM_BOT_TOKEN}/sendMessage" \
        --data-urlencode "chat_id=${ALERT_TELEGRAM_CHAT_ID}" \
        --data-urlencode "text=${text}" \
        --data-urlencode "disable_web_page_preview=true" \
        2>&1)" || {
        log_warn "alert_telegram: curl failed: ${resp}"
        return 1
    }
    if printf '%s' "${resp}" | grep -q '"ok":true'; then
        log_info "alert_telegram: sent"
        return 0
    fi
    log_warn "alert_telegram: API returned: ${resp}"
    return 1
}

alert_email() {
    local subject="$1"
    local body="$2"
    if [ -z "${ALERT_EMAIL_TO:-}" ] \
        || [ -z "${ALERT_EMAIL_FROM:-}" ] \
        || [ -z "${ALERT_SMTP_URL:-}" ]; then
        return 1
    fi

    local tmp
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' EXIT
    {
        printf 'From: %s\n' "${ALERT_EMAIL_FROM}"
        printf 'To: %s\n' "${ALERT_EMAIL_TO}"
        printf 'Subject: [EspoDental] %s\n' "${subject}"
        printf 'Content-Type: text/plain; charset=UTF-8\n'
        printf '\n'
        printf '%s\n' "${body}"
    } > "${tmp}"

    local args="--silent --max-time 30 --url ${ALERT_SMTP_URL} \
        --mail-from ${ALERT_EMAIL_FROM} \
        --mail-rcpt ${ALERT_EMAIL_TO} \
        --upload-file ${tmp} --ssl-reqd"
    if [ -n "${ALERT_SMTP_USER:-}" ]; then
        args="${args} --user ${ALERT_SMTP_USER}:${ALERT_SMTP_PASS:-}"
    fi

    # shellcheck disable=SC2086
    if curl ${args} 2>&1; then
        log_info "alert_email: sent to ${ALERT_EMAIL_TO}"
        rm -f "${tmp}"
        trap - EXIT
        return 0
    fi
    log_warn "alert_email: curl SMTP failed"
    rm -f "${tmp}"
    trap - EXIT
    return 1
}
