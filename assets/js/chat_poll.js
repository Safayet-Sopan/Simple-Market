(function () {
    var POLL_INTERVAL_MS = 3000;

    var box = document.getElementById('chat-messages');
    var form = document.getElementById('chat-form');
    var input = document.getElementById('chat-input');
    var statusLine = document.getElementById('chat-status');

    if (!box || !form || !input) {
        return;
    }

    var orderId = box.getAttribute('data-order-id');
    var pollUrl = box.getAttribute('data-poll-url');
    var sendUrl = box.getAttribute('data-send-url');
    var lastId = 0;
    var polling = false;

    if (!pollUrl || !sendUrl) {
        setStatus('Chat is unavailable on this page.');
        return;
    }

    function setStatus(text) {
        if (statusLine) {
            statusLine.textContent = text;
        }
    }

    function append(message) {
        var row = document.createElement('p');
        row.className = message.is_mine ? 'chat-mine' : 'chat-theirs';

        var who = document.createElement('strong');
        who.textContent = message.sender + ' (' + message.role + '): ';
        row.appendChild(who);

        var body = document.createElement('span');
        body.textContent = message.text;
        row.appendChild(body);

        var when = document.createElement('small');
        when.textContent = ' ' + message.sent_at;
        row.appendChild(when);

        box.appendChild(row);
    }

    function poll() {
        if (polling) {
            return;
        }
        polling = true;

        fetch(pollUrl + '&order_id=' + encodeURIComponent(orderId) + '&after_id=' + lastId,
              { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Poll failed: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var atBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 40;

                data.messages.forEach(function (message) {
                    append(message);
                    if (message.message_id > lastId) {
                        lastId = message.message_id;
                    }
                });

                if (data.messages.length > 0 && atBottom) {
                    box.scrollTop = box.scrollHeight;
                }
                setStatus('');
            })
            .catch(function () {
                setStatus('Connection hiccup — retrying.');
            })
            .then(function () {
                polling = false;
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var text = input.value.trim();
        if (text === '') {
            return;
        }

        var body = new FormData();
        body.append('order_id', orderId);
        body.append('message_text', text);
        body.append('csrf_token', form.getAttribute('data-csrf') || '');

        input.disabled = true;
        setStatus('Sending…');

        fetch(sendUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || 'Send failed');
                    }
                    return data;
                });
            })
            .then(function () {
                input.value = '';
                setStatus('');
                poll();
            })
            .catch(function (error) {
                setStatus(error.message);
            })
            .then(function () {
                input.disabled = false;
                input.focus();
            });
    });

    poll();
    setInterval(poll, POLL_INTERVAL_MS);
})();
