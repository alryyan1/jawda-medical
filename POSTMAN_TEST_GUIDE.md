# Postman Test Guide for Ultramsg Send Document API

## Endpoint Details

**URL:** `http://localhost/jawda-medical/public/api/ultramsg/send-document-with-credentials`

**Method:** `POST`

**Authentication:** None required (credentials are sent in the request body)

---

## Option 1: Using Base64 (Recommended for Testing)

### Step 1: Set Request Type
1. Open Postman
2. Create a new request
3. Set method to **POST**
4. Enter URL: `http://localhost/jawda-medical/public/api/ultramsg/send-document-with-credentials`

### Step 2: Set Headers
Go to **Headers** tab and add:
```
Content-Type: application/json
Accept: application/json
```

### Step 3: Set Body
1. Go to **Body** tab
2. Select **raw**
3. Select **JSON** from dropdown
4. Paste the following JSON (replace with your actual values):

```json
{
    "token": "wjav78swzp...",
    "instance_id": "instance140372",
    "phone": "249991961111",
    "base64": "JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwo+PgplbmRvYmoKeHJlZgoxIDAKdHJhaWxlcgo8PAovU2l6ZSAxCi9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgo5CiUlRU9G",
    "caption": "labresult"
}
```

### Step 4: Send Request
Click **Send** button

---

## Option 2: Using File Upload

### Step 1: Set Request Type
1. Open Postman
2. Create a new request
3. Set method to **POST**
4. Enter URL: `http://localhost/jawda-medical/public/api/ultramsg/send-document-with-credentials`

### Step 2: Set Body
1. Go to **Body** tab
2. Select **form-data**
3. Add the following fields:

| Key | Type | Value |
|-----|------|-------|
| `token` | Text | `wjav78swzp...` (your token) |
| `instance_id` | Text | `instance140372` (your instance ID) |
| `phone` | Text | `249991961111` |
| `file` | File | Select a PDF file from your computer |
| `caption` | Text | `labresult` (optional, defaults to "labresult") |

**Note:** Do NOT include `base64` field when using file upload.

### Step 3: Send Request
Click **Send** button

---

## Expected Success Response

```json
{
    "success": true,
    "data": {
        "sent": "true",
        "message": "ok",
        "id": 58431
    },
    "message_id": 58431
}
```

**Status Code:** `200 OK`

---

## Expected Error Responses

### Validation Error (422)
```json
{
    "success": false,
    "error": "Validation failed",
    "errors": {
        "token": ["The token field is required."],
        "phone": ["The phone field is required."]
    }
}
```

### Missing File/Base64 (422)
```json
{
    "success": false,
    "error": "Either file or base64 must be provided"
}
```

### Invalid Phone Number (400)
```json
{
    "success": false,
    "error": "Invalid phone number format"
}
```

---

## How to Get Base64 from a PDF File

### Using Online Tool:
1. Go to https://base64.guru/converter/encode/pdf
2. Upload your PDF file
3. Copy the base64 string

### Using Command Line (Windows PowerShell):
```powershell
$bytes = [System.IO.File]::ReadAllBytes("C:\path\to\your\file.pdf")
$base64 = [System.Convert]::ToBase64String($bytes)
$base64
```

### Using PHP:
```php
$base64 = base64_encode(file_get_contents('path/to/file.pdf'));
echo $base64;
```

---

## Quick Test Values

Replace these with your actual values from the database:

- **Token:** Get from `settings` table, column `ultramsg_token`
- **Instance ID:** Get from `settings` table, column `ultramsg_instance_id`
- **Phone:** Format: `249991961111` (will be converted to `+249991961111`)

---

## Notes

1. **Either `file` OR `base64` is required** - you cannot send both
2. **Phone number** can be in various formats:
   - `249991961111` → Will be converted to `+249991961111`
   - `0991961111` → Will be converted to `+249991961111`
   - `+249991961111` → Will be used as is
3. **Caption** defaults to `"labresult"` if not provided
4. **File size limit:** 30MB (30720 KB)
5. **Caption max length:** 1024 characters


