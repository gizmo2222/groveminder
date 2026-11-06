"""Minimal local SMTP sink for the PHP tests: accepts every message on
127.0.0.1:<port> and appends the raw SMTP transcript to <logfile>."""
import socket
import sys
import threading

port, log = int(sys.argv[1]), sys.argv[2]
lock = threading.Lock()


def handle(conn):
    f = conn.makefile('rwb')

    def say(line):
        f.write(line.encode() + b'\r\n')
        f.flush()

    say('220 sink')
    in_data = False
    buf = []
    for raw in f:
        line = raw.decode('utf-8', 'replace').rstrip('\r\n')
        if in_data:
            if line == '.':
                in_data = False
                with lock, open(log, 'a', encoding='utf-8') as out:
                    out.write('\n'.join(buf) + '\n=====END=====\n')
                buf = []
                say('250 ok')
            else:
                buf.append(line)
            continue
        cmd = line[:4].upper()
        if cmd in ('MAIL', 'RCPT'):
            buf.append('>> ' + line)
        if cmd == 'DATA':
            in_data = True
            say('354 go')
        elif cmd == 'QUIT':
            say('221 bye')
            break
        else:
            say('250 ok')
    conn.close()


s = socket.socket()
s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', port))
s.listen(5)
while True:
    c, _ = s.accept()
    threading.Thread(target=handle, args=(c,), daemon=True).start()
