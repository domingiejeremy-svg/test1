"""
Build plugin zip with Linux-style forward slashes (required for Hostinger).
Python zipfile always uses forward slashes.
"""
import os
import zipfile

SRC = 'wp-wheel-game'
OUT = 'wp-wheel-game-2.0.0.zip'

# Clean previous
if os.path.exists(OUT):
    os.remove(OUT)

with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(SRC):
        for file in files:
            filepath = os.path.join(root, file)
            # Force forward slashes in archive names
            arcname = filepath.replace(os.sep, '/')
            zf.write(filepath, arcname)
            print(f'  {arcname}')

size_kb = os.path.getsize(OUT) / 1024
print(f'\nCreated {OUT} ({size_kb:.1f} KB)')
