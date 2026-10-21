import os
from pathlib import Path
from typing import Dict, Any, List
import config

class YoloObjectDetector:
    """
    Wrapper Deteksi Objek YOLO untuk PLN UP3 Tegal.
    Menangani pemeriksaan keberadaan model bobot kustom secara nyata tanpa membuat deteksi palsu.
    """
    def __init__(self):
        self.model_path = Path(config.MODEL_PATH)
        self.target_classes = config.TARGET_CLASSES
        self.model = None
        self.is_custom_model_loaded = False
        self._initialize_model()

    def _initialize_model(self):
        """
        Memeriksa apakah model YOLO kustom yang valid telah tersedia di penyimpanan.
        Jika belum, sistem secara transparan menetapkan status 'not_loaded'.
        """
        if not self.model_path.exists():
            self.is_custom_model_loaded = False
            return

        try:
            # Jika ultralytics tersedia dan model file ada
            from ultralytics import YOLO
            self.model = YOLO(str(self.model_path))
            self.is_custom_model_loaded = True
        except ImportError:
            self.is_custom_model_loaded = False
        except Exception as e:
            self.is_custom_model_loaded = False

    def get_health_status(self) -> Dict[str, Any]:
        """
        Mengembalikan informasi status kesiapan model dan layanan.
        """
        model_exists = self.model_path.exists()
        return {
            "status": "healthy",
            "service": "PLN UP3 Tegal - Layanan Deteksi Objek YOLO",
            "version": "1.0.0",
            "host": config.HOST,
            "port": config.PORT,
            "target_classes": self.target_classes,
            "model_path": str(self.model_path),
            "model_file_exists": model_exists,
            "model_loaded": self.is_custom_model_loaded,
            "is_production_ready": self.is_custom_model_loaded,
            "notice": (
                "Model kustom siap untuk produksi."
                if self.is_custom_model_loaded
                else "Model kustom khusus (kWh, Tiang, Trafo) belum dilatih/tersedia. "
                     "Sistem tidak menampilkan deteksi AI tiruan/palsu."
            ),
        }

    def detect(self, image_bytes: bytes, image_name: str = "", simulate_integration: bool = False) -> Dict[str, Any]:
        """
        Menjalankan deteksi pada gambar yang dikirimkan.
        - Jika model kustom belum ada dan simulate_integration = False:
          Mengembalikan status 'model_not_trained' dan menolak merekayasa hasil.
        - Jika simulate_integration = True:
          Digunakan khusus untuk menguji konektivitas alur data bolak-balik Laravel <-> Python
          dengan label 'is_simulation': True.
        """
        # Validasi dasar berkas gambar
        image_size = len(image_bytes)
        if image_size == 0:
            return {
                "success": False,
                "error": "Ukuran gambar kosong (0 bytes).",
                "detections": [],
                "is_production_ready": False,
            }

        # Skenario 1: Model Kustom Nyata Tersedia
        if self.is_custom_model_loaded and self.model is not None:
            import io
            from PIL import Image
            img = Image.open(io.BytesIO(image_bytes))
            results = self.model.predict(img, conf=config.CONFIDENCE_THRESHOLD)
            
            detections: List[Dict[str, Any]] = []
            for r in results:
                boxes = r.boxes
                for box in boxes:
                    cls_id = int(box.cls[0])
                    cls_name = self.model.names.get(cls_id, str(cls_id))
                    conf = float(box.conf[0])
                    xyxy = [float(coord) for coord in box.xyxy[0]]
                    
                    # Pastikan kelas masuk dalam target kelas PLN
                    if cls_name in self.target_classes:
                        detections.append({
                            "class": cls_name,
                            "confidence": round(conf, 4), # Rentang 0.0 sampai 1.0
                            "bbox": xyxy,
                            "is_verified_class": True,
                        })

            return {
                "success": True,
                "is_production_ready": True,
                "model_status": "custom_trained_weights",
                "image_name": image_name,
                "detections_count": len(detections),
                "detections": detections,
            }

        # Skenario 2: Simulasi Uji Integrasi Jalur Komunikasi (Diberi label eksplisit)
        if simulate_integration:
            # Deteksi inferensi indikatif untuk pengujian komunikasi
            # Nilai confidence berada di rentang 0.0 s.d 1.0
            inferred_class = "kWh"
            lower_name = image_name.lower()
            if "tiang" in lower_name:
                inferred_class = "Tiang"
            elif "trafo" in lower_name:
                inferred_class = "Trafo"

            return {
                "success": True,
                "is_production_ready": False,
                "is_simulation": True,
                "model_status": "integration_test_stub",
                "message": "[DATA SIMULASI INTEGRASI] Pengujian alur komunikasi HTTP Laravel ke Python berhasil.",
                "image_name": image_name,
                "detections_count": 1,
                "detections": [
                    {
                        "class": inferred_class,
                        "confidence": 0.8950, # Valid float antara 0.0 dan 1.0
                        "bbox": [50.0, 45.0, 480.0, 420.0],
                        "is_verified_class": False,
                        "notes": "Hasil dari stub pengujian integrasi konektivitas API.",
                    }
                ],
            }

        # Skenario 3: Model Kustom Belum Tersedia (Kejujuran Arsitektur)
        return {
            "success": False,
            "is_production_ready": False,
            "status": "custom_model_not_found",
            "message": (
                f"Model bobot YOLO khusus (kWh, Tiang, Trafo) belum tersedia di '{self.model_path}'. "
                "Sistem menolak membuat hasil deteksi fiktif. Silakan sediakan dataset berlabel "
                "dan latih model kustom terlebih dahulu."
            ),
            "target_classes": self.target_classes,
            "image_name": image_name,
            "detections": [],
        }

# Singleton instance
detector = YoloObjectDetector()
