#!/usr/bin/env python3
"""
HEIC Image Converter CLI Fallback
Sistem Stok & Serah Terima Barang Logistik
Converts HEIC / HEIF image files to WebP or JPEG format.
"""

import sys
import os

def convert_heic(input_path, output_path):
    if not os.path.exists(input_path):
        print(f"Error: File input '{input_path}' tidak ditemukan.", file=sys.stderr)
        return False

    try:
        # Try pillow_heif if available
        import pillow_heif
        from PIL import Image

        pillow_heif.register_heif_opener()
        img = Image.open(input_path)
        img.save(output_path, quality=85, optimize=True)
        print(f"Sukses mengonversi {input_path} -> {output_path}")
        return True
    except Exception as e1:
        # Fallback using pyheif or direct Image if registered
        try:
            from PIL import Image
            img = Image.open(input_path)
            img.save(output_path, quality=85)
            print(f"Sukses mengonversi (Pillow) {input_path} -> {output_path}")
            return True
        except Exception as e2:
            print(f"Error Konversi HEIC: {e1} / {e2}", file=sys.stderr)
            return False

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Penggunaan: python convert_heic.py <input_heic_path> <output_webp_path>")
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]

    success = convert_heic(input_file, output_file)
    if success:
        sys.exit(0)
    else:
        sys.exit(1)
