# ProtoWeb Dynamic Chat — WebSocket Service

This software provides the **WebSocket service**  
required by the [ProtoWeb][PW] dynamic chat system.
The service acts as a relay between clients and the backend PHP,  
using [WebSocketd][WSD].

> This service works standalone by default, saving messages as JSON files.  
> **Integration with [CodeIgniter 3][CI3] is optional** for SQL-based storage.

---

## 📦 Install

> Recommended install location: `/srv/websocket/` or `/var/www/websocket/`  
> Do **not** install in `/etc/websocket/`, which is for configuration only.

### 1. Clone this source code

    ```bash
    git clone https://github.com/msilva88-dev/protoweb-ws_php.git
    cd /srv/websocket/protoweb-ws_php
    ```

### 2. Generate the `vendor/` directory

    ```bash
    composer install
    composer dump-autoload --optimize
    ```

### 3. Run the WebSocket service

    ```bash
    nohup websocketd --address=[::] --port=8080 /srv/websocket/protoweb-ws_php/wsl.php ProtoWeb \
        > /tmp/error.log 2>&1 &
    ```

---

## 🧾 Description

This WebSocket service supports:

- Real-time messaging using WebSocket.
- Lightweight file-based storage with JSON (default).
- Optional SQL database support (incomplete).
- Optional backend integration with [CodeIgniter 3][CI3].

See the [Protoweb][PWCI3] if you want SQL integration.

---

## ⚖ License

BSD 2-Clause License — see [LICENSE][LIC]

[PW]: https://example.com
    "ProtoWeb main website"
[CI3]: https://codeigniter.com
    "CodeIgniter 3 official site"
[WSD]: https://github.com/joewalnes/websocketd
    "WebSocketd on GitHub"
[PWCI3]: https://github.com/msilva88-dev/protoweb-ci3
    "ProtoWeb CI3 backend"
[LIC]: LICENSE
    "BSD 2-Clause License file"
