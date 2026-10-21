# Direktori Model Bobot YOLO (PLN UP3 Tegal)

Folder ini digunakan untuk menyimpan berkas bobot model hasil pelatihan (*trained weights*) berformat PyTorch (`.pt`).

## Nama Berkas yang Diharapkan
- `pln_kwh_tiang_trafo.pt` (atau sesuaikan variabel `YOLO_MODEL_PATH` pada file `.env`).

## Target Kelas Deteksi
Urutan kelas (`classes`) pada konfigurasi pelatihan YOLO:
1. `0: kWh` (Foto meteran listrik prabayar/pascabayar)
2. `1: Tiang` (Tiang beton/besi saluran udara distribusi)
3. `2: Trafo` (Transformator distribusi tiang / gardu cantol & portal)

## Prosedur Pelatihan Model YOLOv8 Kustom
1. Kumpulkan minimal 300 - 500 foto per kelas dari wilayah kerja PLN UP3 Tegal dengan variasi cuaca, sudut pandang, dan jarak pemotretan.
2. Beri label bounding box menggunakan format YOLO (`class_id x_center y_center width height`).
3. Latih menggunakan Ultralytics YOLOv8:
   ```bash
   yolo detect train data=pln_dataset.yaml model=yolov8n.pt epochs=50 imgsz=640
   ```
4. Salin berkas `runs/detect/train/weights/best.pt` ke dalam folder ini dengan nama `pln_kwh_tiang_trafo.pt`.
5. Mulai ulang (*restart*) layanan `ai_service/app.py`.
