"""Prints the messages in the SMTP sink's log that were sent to one address, each
under a '### MESSAGE' line, with the encoded Subject decoded.
Usage: python mail_to.py <logfile> <address>   (the address match ignores case)"""
import base64
import re
import sys

sys.stdout.reconfigure(newline='\n')  # plain LF even on Windows, so callers can match multi-line text
log, addr = sys.argv[1], sys.argv[2].lower()
text = open(log, encoding='utf-8').read()
for msg in text.split('=====END=====\n'):
    if '>> rcpt to:<' + addr + '>' not in msg.lower():
        continue
    msg = re.sub(r'=\?UTF-8\?B\?([^?]*)\?=', lambda m: base64.b64decode(m.group(1)).decode('utf-8'), msg)
    print('### MESSAGE')
    print(msg)
