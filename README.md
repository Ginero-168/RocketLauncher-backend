# Rocket Launcher Marketplace

เว็บกลางสำหรับ Script, Color และ Graphic Style ของพาเนล Rocket Launcher ใช้บน Hostinger shared hosting ได้

เว็บนี้แสดงรายการอย่างเดียว สมาชิกเข้าสู่ระบบด้วย Google แล้วกด Star เพื่อเก็บรายการเข้า Library ของตัวเอง การเพิ่มและลบทำจากพาเนลด้วยโทเคนอัปโหลดเท่านั้น

## ชนิดรายการ

| ชนิด | สิ่งที่เก็บ |
|---|---|
| Script | ไฟล์ `.jsx` ไม่เกิน 2 MB |
| Color | รหัสสี 1–8 สี ไม่มีไฟล์ |
| Graphic Style | ไฟล์ `.ai` ไม่เกิน 40 MB และรูปตัวอย่างคำว่า TEXT เป็น PNG หรือ JPEG ไม่เกิน 2 MB |

## API

| ทาง | งาน |
|---|---|
| `GET /api/list.php` | รายการทั้งหมด เป็น JSON ใส่ `?type=script` `color` หรือ `style` ได้ |
| `GET /api/library.php` | รายการที่ผู้ใช้ Star ไว้ ต้องมีเซสชันที่ล็อกอินแล้ว |
| `GET /api/download.php?id=` | ดาวน์โหลด Script หรือ Graphic Style Color ส่ง JSON ของชุดสี |
| `GET /api/preview.php?id=` | รูปตัวอย่างของ Graphic Style |
| `POST /api/upload.php` | เพิ่มรายการจากพาเนล ต้องใช้โทเคนอัปโหลด |
| `POST /api/star.php` | สลับ Star ต้องล็อกอินและส่ง `csrf` |
| `POST /api/delete.php` | ลบรายการจากพาเนล ต้องใช้โทเคนอัปโหลด |

อัปโหลดส่งแบบ `multipart/form-data` ด้วยฟิลด์ `type` (`script`, `color`, `style`), `name`, `description` Color ใช้ฟิลด์ `colors` Graphic Style ใช้ `file` และ `preview` Script ใช้ `file` โทเคนส่งเป็นฟิลด์ `token` หรือหัว `X-Upload-Token`

## ขึ้น Hostinger ผ่าน GitHub

1. ใน hPanel เปิด Git แล้วเชื่อม repo นี้ ชี้โดเมนมาที่รากของ repo
2. เปิด `https://โดเมน/setup.php` หนึ่งครั้ง แล้วเก็บโทเคนที่แสดง
3. ใน Google Cloud สร้าง OAuth client ชนิด Web แล้วใส่ Redirect URI ที่หน้า setup แสดง
4. กลับมาที่ `setup.php` ใส่โทเคนเดิม, ที่อยู่เว็บ, client id และ client secret
5. ลบ `setup.php` บนเซิร์ฟเวอร์หลังเก็บค่าแล้ว

การดึง Git จะไม่ทับ `config.json` กับไฟล์ใน `storage/` เพราะไฟล์พวกนั้นไม่อยู่ใน repo อย่าลบโฟลเดอร์ `storage` ตอน deploy

## ตัวอย่างรายการ

```json
{
  "ok": true,
  "items": [
    {
      "id": "0123456789abcdef",
      "type": "style",
      "name": "Gold",
      "description": "",
      "filename": "Gold.ai",
      "bytes": 120000,
      "created": "2026-10-18T03:00:00+00:00",
      "colors": [],
      "preview": true,
      "owner_name": "Peerawat",
      "starred": false,
      "stars": 3,
      "mine": false
    }
  ]
}
```
