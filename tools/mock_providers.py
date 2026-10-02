#!/usr/bin/env python3
"""Local HTTP fixtures for exercising the JSON and XML debt provider adapters."""

from http.server import BaseHTTPRequestHandler, HTTPServer
from datetime import date, timedelta
from urllib.parse import parse_qs, urlparse


today = date.today()
DEBTS = [
    {"id": "ipva-1", "type": "IPVA", "amount": "1200.00", "due_date": (today - timedelta(days=29)).isoformat()},
    {"id": "ipva-2", "type": "IPVA", "amount": "350.50", "due_date": (today + timedelta(days=30)).isoformat()},
    {"id": "multa-1", "type": "MULTA", "amount": "195.23", "due_date": (today - timedelta(days=20)).isoformat()},
]


class MockProviderHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        request = urlparse(self.path)
        plate = parse_qs(request.query).get("plate", [""])[0].upper()

        if request.path.startswith("/provider-a"):
            # Use ZZZ0000 to make A fail and demonstrate fallback to Provider B.
            if plate in {"ZZZ0000", "ZZZ9999"}:
                self.send_error(503, "Simulated Provider A outage")
                return
            import json

            body = json.dumps({"debts": [] if plate == "ZER0000" else DEBTS}).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
        elif request.path.startswith("/provider-b"):
            # Use ZZZ9999 to make both providers fail and exercise the API 503 response.
            if plate == "ZZZ9999":
                self.send_error(503, "Simulated Provider B outage")
                return
            entries = "".join(
                "<debt>"
                f"<id>{debt['id']}</id><type>{debt['type']}</type>"
                f"<amount>{debt['amount']}</amount><due_date>{debt['due_date']}</due_date>"
                "</debt>"
                for debt in ([] if plate == "ZER0000" else DEBTS)
            )
            body = f"<?xml version='1.0' encoding='UTF-8'?><debts>{entries}</debts>".encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/xml; charset=utf-8")
        else:
            self.send_error(404, "Use /provider-a or /provider-b")
            return

        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        print(f"mock-provider: {self.address_string()} {format % args}")


if __name__ == "__main__":
    print("Mock providers listening on http://0.0.0.0:8001")
    print("Provider A: /provider-a?plate=ABC1234 (JSON)")
    print("Provider B: /provider-b?plate=ABC1234 (XML)")
    HTTPServer(("0.0.0.0", 8001), MockProviderHandler).serve_forever()
