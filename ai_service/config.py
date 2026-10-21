import os
from pathlib import Path

# Direktori dasar AI Service
BASE_DIR = Path(__file__).resolve().parent

# Server Binding (Hanya diizinkan dari loopback 127.0.0.1 demi keamanan)
HOST = os.getenv("AI_SERVICE_HOST", "127.0.0.1")
PORT = int(os.getenv("AI_SERVICE_PORT", 8001))

# Keamanan Komunikasi API
API_KEY = os.getenv("AI_SERVICE_API_KEY", "pln-tegal-internal-token-2026")

# Jalur Berkas Model YOLO Kustom
DEFAULT_MODEL_PATH = BASE_DIR / "models" / "pln_kwh_tiang_trafo.pt"
MODEL_PATH = os.getenv("YOLO_MODEL_PATH", str(DEFAULT_MODEL_PATH))

# Target Kelas Objek PLN UP3 Tegal
TARGET_CLASSES = ["kWh", "Tiang", "Trafo"]

# Ambang Batas Default
CONFIDENCE_THRESHOLD = float(os.getenv("CONFIDENCE_THRESHOLD", 0.50))
