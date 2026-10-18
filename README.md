# Rocket Launcher Marketplace

เว็บกลางสำหรับแจก Script (`.jsx`) และ Appearance (`.ai`) ให้พาเนล Rocket Launcher ใช้บน Hostinger shared hosting ได้

## API

| ทาง | งาน |
|---|---|
| `GET /api/list.php` | รายการทั้งหมด เป็น JSON |
| `GET /api/download.php?id=` | ดาวน์โหลดไฟล์ |
| `POST /api/upload.php` | เพิ่มรายการ ต้องมีโทเคน |
| `POST /api/delete.php` | ลบรายการ ต้องมีโทเคน |

อัปโหลดส่งแบบ `multipart/form-data` ด้วยฟิลด์ `token`, `type` (`script` หรือ `appearance`), `name`, `description`, `file` โทเคนส่งเป็นหัว `X-Upload-Token` ก็ได้

Script รับเฉพาะ `.jsx` ไม่เกิน 2 MB Appearance รับเฉพาะ `.ai` ไม่เกิน 40 MB

## ขึ้น Hostinger ผ่าน GitHub

1. ใน hPanel เปิด Git แล้วเชื่อม repo นี้ ชี้โดเมนมาที่รากของ repo
2. เปิด `https://โดเมน/setup.php` หนึ่งครั้ง แล้วเก็บโทเคนที่แสดง
3. ลบ `setup.php` บนเซิร์ฟเวอร์หลังเก็บโทเคนแล้ว
4. ใส่โทเคนในพาเนลตอนอัปโหลด อย่าคอมมิต `config.json`

การดึง Git จะไม่ทับ `config.json` กับไฟล์ใน `storage/` เพราะไฟล์พวกนั้นไม่อยู่ใน repo อย่าลบโฟลเดอร์ `storage` ตอน deploy

## ตัวอย่างรายการ

```json
{
  "ok": true,
  "items": [
    {
      "id": "0123456789abcdef",
      "type": "appearance",
      "name": "Gold",
      "description": "",
      "filename": "Gold.ai",
      "bytes": 120000,
      "created": "2026-10-18T03:00:00+00:00"
    }
  ]
}
```
