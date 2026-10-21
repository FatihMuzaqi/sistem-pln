"""
Layanan REST API Python untuk Deteksi Objek YOLO & OCR Tulisan kWh Meter
PT PLN (Persero) UP3 Tegal
"""

import json
import io
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler
import urllib.parse
import config
from detector import detector
from ocr_engine import ocr_engine

class PlnAiApiRequestHandler(BaseHTTPRequestHandler):
    """
    HTTP Request Handler mandiri yang bekerja secara langsung tanpa memerlukan
    instalasi pustaka eksternal yang rumit di Windows, serta sepenuhnya kompatibel
    dengan standar REST API modern (FastAPI format).
    """

    def _set_headers(self, status_code=200, content_type="application/json"):
        self.send_response(status_code)
        self.send_header("Content-Type", content_type)
        # Batasi akses CORS hanya untuk lingkungan lokal yang diizinkan
        self.send_header("Access-Control-Allow-Origin", f"http://{config.HOST}:8000")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-API-Key, X-Simulation-Test, X-Image-Name, X-OCR-Sample")
        self.end_headers()

    def do_OPTIONS(self):
        self._set_headers(204)

    def _verify_api_key(self) -> bool:
        """
        Validasi autentikasi API Key dari Header atau Query string.
        """
        auth_header = self.headers.get("X-API-Key")
        if auth_header and auth_header == config.API_KEY:
            return True

        # Izinkan akses health check lokal untuk monitoring
        if self.path.startswith("/health") or self.path.startswith("/api/v1/health"):
            return True

        return False

    def do_GET(self):
        parsed_url = urllib.parse.urlparse(self.path)
        path = parsed_url.path

        # Endpoint 1: Pemeriksaan Kesehatan Layanan (Health Check)
        if path in ["/", "/health", "/api/v1/health"]:
            health_data = detector.get_health_status()
            # Sertakan status mesin OCR
            health_data["ocr_engine"] = ocr_engine.engine_name
            health_data["ocr_supported_classes"] = ["kWh"]
            self._set_headers(200)
            self.wfile.write(json.dumps(health_data, indent=2).encode("utf-8"))
            return

        self._set_headers(404)
        self.wfile.write(json.dumps({"error": "Endpoint tidak ditemukan."}).encode("utf-8"))

    def _extract_image_bytes(self, content_type: str, body: bytes) -> bytes:
        """
        Mengekstrak bytes gambar dari multipart/form-data atau raw binary
        """
        if "multipart/form-data" in content_type:
            boundary = content_type.split("boundary=")[-1].encode("utf-8")
            parts = body.split(boundary)
            for part in parts:
                if b"Content-Disposition" in part and (b'name="image"' in part or b'name="file"' in part or b'filename=' in part):
                    headers_and_body = part.split(b"\r\n\r\n", 1)
                    if len(headers_and_body) == 2:
                        return headers_and_body[1].rstrip(b"\r\n--")
            return body
        return body

    def do_POST(self):
        parsed_url = urllib.parse.urlparse(self.path)
        path = parsed_url.path

        # Validasi API Key
        if not self._verify_api_key():
            self._set_headers(401)
            self.wfile.write(json.dumps({
                "success": False,
                "error": "Akses ditolak: X-API-Key tidak valid atau tidak disertakan.",
            }).encode("utf-8"))
            return

        content_type = self.headers.get("Content-Type", "")
        content_length = int(self.headers.get("Content-Length", 0))

        if content_length == 0:
            self._set_headers(400)
            self.wfile.write(json.dumps({
                "success": False,
                "error": "Permintaan kosong: Tidak ada berkas gambar yang dikirimkan.",
            }).encode("utf-8"))
            return

        body = self.rfile.read(content_length)
        image_bytes = self._extract_image_bytes(content_type, body)
        simulate_header = self.headers.get("X-Simulation-Test", "false").lower() == "true"
        image_name = self.headers.get("X-Image-Name", "photo_input.jpg")
        ocr_sample_header = self.headers.get("X-OCR-Sample", None)

        # Endpoint 2: Deteksi Objek YOLO Terpadu dengan Auto-OCR untuk kWh
        if path in ["/detect", "/api/v1/detect"]:
            result = detector.detect(
                image_bytes=image_bytes,
                image_name=image_name,
                simulate_integration=simulate_header,
            )

            # Jika deteksi berhasil / simulasi menghasilkan kelas 'kWh', jalankan crop dan OCR otomatis
            detections = result.get("detections", [])
            primary_class = detections[0]["class"] if detections else "kWh"
            primary_bbox = detections[0].get("bbox", []) if detections else []

            if primary_class == "kWh":
                # Jalankan OCR pada area crop kWh meter
                ocr_result = ocr_engine.process_kwh_ocr(
                    image_bytes=image_bytes,
                    bbox=primary_bbox,
                    image_name=image_name,
                    simulate_sample=ocr_sample_header
                )
                result["ocr"] = ocr_result
            else:
                # Sesuai aturan: Pastikan OCR tidak berjalan untuk foto non-kWh (Tiang & Trafo)
                result["ocr"] = None

            status_code = 200 if result.get("success") or result.get("status") == "custom_model_not_found" else 400
            self._set_headers(status_code)
            self.wfile.write(json.dumps(result, indent=2).encode("utf-8"))
            return

        # Endpoint 3: Eksekusi OCR Mandiri pada Foto kWh
        if path in ["/ocr", "/api/v1/ocr"]:
            ocr_result = ocr_engine.process_kwh_ocr(
                image_bytes=image_bytes,
                bbox=None,
                image_name=image_name,
                simulate_sample=ocr_sample_header
            )
            self._set_headers(200)
            self.wfile.write(json.dumps(ocr_result, indent=2).encode("utf-8"))
            return

        self._set_headers(404)
        self.wfile.write(json.dumps({"error": "Endpoint tidak ditemukan."}).encode("utf-8"))

def run(host=config.HOST, port=config.PORT):
    server_address = (host, port)
    httpd = HTTPServer(server_address, PlnAiApiRequestHandler)
    print(f"============================================================")
    print(f"  PLN UP3 TEGAL - LAYANAN AI (YOLO DETECT + OCR KWH)")
    print(f"============================================================")
    print(f"  Status        : Berjalan pada http://{host}:{port}")
    print(f"  Health Check  : http://{host}:{port}/health")
    print(f"  Detect API    : http://{host}:{port}/api/v1/detect")
    print(f"  OCR API       : http://{host}:{port}/api/v1/ocr")
    print(f"  Target Kelas  : {', '.join(config.TARGET_CLASSES)}")
    print(f"  Mesin OCR     : {ocr_engine.engine_name}")
    print(f"============================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nMenutup layanan AI...")
        httpd.server_close()

if __name__ == "__main__":
    host = sys.argv[1] if len(sys.argv) > 1 else config.HOST
    port = int(sys.argv[2]) if len(sys.argv) > 2 else config.PORT
    run(host, port)
