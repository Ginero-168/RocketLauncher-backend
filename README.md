# Rocket Launcher Marketplace

เว็บกลางสำหรับ Script, Color และ Graphic Style ของพาเนล Rocket Launcher ใช้บน Hostinger shared hosting ได้

เว็บนี้แสดงรายการอย่างเดียว สมาชิกเข้าสู่ระบบด้วย Google แล้วกด Star เพื่อเก็บรายการเข้า Library ของตัวเอง แต่ละรายการอยู่ได้ 10 วัน มีคนดาวน์โหลดไปใช้แล้วจะต่อเวลาอีก 10 วัน รายการที่หมดอายุจะถูกลบ การเพิ่มทำจากพาเนล หลังผู้ใช้กดเชื่อมต่อด้วย Google หนึ่งครั้ง โทเคนจากหน้า setup เป็นของคนดูแลระบบ ใช้ลบรายการ ไม่ต้องให้ผู้ใช้ใส่

## ชนิดรายการ

| ชนิด | สิ่งที่เก็บ |
|---|---|
| Script | ไฟล์ `.jsx` ไม่เกิน 2 MB |
| Color | รหัสสี 1–8 สี ไม่มีไฟล์ |
| Graphic Style | ไฟล์ `.ai` ไม่เกิน 40 MB และรูปตัวอย่างคำว่า TEXT เป็น PNG หรือ JPEG ไม่เกิน 2 MB |

## ขีดจำกัด

- สมาชิกหนึ่งคนอัปโหลดได้ 20 รายการต่อ 24 ชั่วโมง (เกินแล้วได้ 429)
- พื้นที่รวมของทุกรายการไม่เกิน 2048 MB (เต็มแล้วได้ 507)
- Graphic Style ต้องเป็นไฟล์ Illustrator จริง ไฟล์ต้องขึ้นต้นด้วย `%PDF` หรือ `%!PS-Adobe`

เปลี่ยนค่าได้ใน `config.json` ด้วย `max_uploads_per_day` และ `max_storage_mb`

## API

| ทาง | งาน |
|---|---|
| `GET /api/list.php` | รายการทั้งหมด เป็น JSON ใส่ `?type=script` `color` หรือ `style` ได้ |
| `GET /api/library.php` | รายการที่ผู้ใช้ Star ไว้ ต้องมีเซสชันที่ล็อกอินแล้ว |
| `GET /api/download.php?id=` | ดาวน์โหลด Script หรือ Graphic Style Color ส่ง JSON ของชุดสี การดาวน์โหลดต่ออายุรายการเป็น 10 วันและเพิ่มจำนวนโหลด |
| `GET /api/preview.php?id=` | รูปตัวอย่างของ Graphic Style |
| `POST /api/panel.php` | เริ่มเชื่อมพาเนลกับบัญชี Google ส่งฟิลด์ `action=start` |
| `GET /api/panel.php?device=` | ตรวจว่าผู้ใช้กดอนุญาตบนเว็บแล้วหรือยัง |
| `POST /api/upload.php` | เพิ่มรายการจากพาเนลที่เชื่อมต่อแล้ว ส่ง `panel_token` คนดูแลส่งโทเคนอัปโหลดแทนได้ |
| `POST /api/star.php` | สลับ Star ต้องล็อกอินและส่ง `csrf` |
| `POST /api/delete.php` | ลบรายการ ต้องใช้โทเคนของคนดูแลระบบ |

อัปโหลดส่งแบบ `multipart/form-data` ด้วยฟิลด์ `type` (`script`, `color`, `style`), `name`, `description` Color ใช้ฟิลด์ `colors` Graphic Style ใช้ `file` และ `preview` Script ใช้ `file` พาเนลส่ง `panel_token` ที่ได้หลังกดอนุญาต โทเคนของคนดูแลส่งเป็นฟิลด์ `token` หรือหัว `X-Upload-Token`

## ขึ้น Hostinger ผ่าน GitHub

1. ใน hPanel เปิด Git แล้วเชื่อม repo นี้ ชี้โดเมนมาที่รากของ repo
2. เปิด `https://โดเมน/setup.php` หนึ่งครั้ง แล้วเก็บโทเคนที่แสดง
3. ใน Google Cloud สร้าง OAuth client ชนิด Web แล้วใส่ Redirect URI ที่หน้า setup แสดง
4. กลับมาที่ `setup.php` ใส่โทเคนเดิม, ที่อยู่เว็บ, client id และ client secret
5. ลบ `setup.php` บนเซิร์ฟเวอร์หลังเก็บค่าแล้ว (ถ้ายังไม่ลบ หน้านี้จะปิดตัวเองเมื่อใส่ Google ครบ หลังจากนั้นเปลี่ยนค่าได้ทาง `config.json` ใน File Manager)

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
      "expires": "2026-10-28T03:00:00+00:00",
      "days_left": 10,
      "downloads": 12,
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
