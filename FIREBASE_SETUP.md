# Firebase Setup Instructions

## Overview
This application uses Firebase Storage to store lab result PDFs. The `UploadLabResultToFirebase` job uploads generated PDFs to Firebase Storage and returns the proper download URL.

## Setup Steps

### 1. Firebase Project Configuration
Add the following environment variables to your `.env` file:

```env
# Firebase Configuration
FIREBASE_PROJECT_ID=hospitalapp-681f1
FIREBASE_STORAGE_BUCKET=hospitalapp-681f1.firebasestorage.app
FIREBASE_HOSPITAL_NAME="Jawda Medical"
FIREBASE_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json
```

### 2. Firebase Service Account
1. Go to your Firebase Console: https://console.firebase.google.com/
2. Select your project: `hospitalapp-681f1`
3. Go to Project Settings > Service Accounts
4. Click "Generate new private key"
5. Download the JSON file
6. Save it as `storage/app/firebase-service-account.json` in your Laravel project

**Important:** Make sure the file is named exactly `firebase-service-account.json` (not `.json.example`)

**Example file structure:**
```
storage/
  app/
    firebase-service-account.json  ← Your actual service account file
    firebase-service-account.json.example  ← Example file (for reference)
```

### 3. Firebase Storage Rules
Make sure your Firebase Storage rules allow uploads. Example rules:

```javascript
rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {
    match /results/{hospitalName}/{visitId}/{fileName} {
      allow read, write: if true; // Adjust based on your security needs
    }
  }
}
```

### 4. Test the Setup
After setting up the configuration:

1. Make sure the queue worker is running:
   ```bash
   php artisan queue:work
   ```

2. Authenticate a lab result in the application
3. Check the logs to see if the upload was successful:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Expected URL Format
After successful upload, the `result_url` field in the patients table should contain a URL like:
```
https://firebasestorage.googleapis.com/v0/b/hospitalapp-681f1.firebasestorage.app/o/results%2FJawda%20Medical%2F23030%2Flab_result_23030___________2025-09-19.pdf?alt=media&token=83c780b8-ec41-4263-acfa-9e122caec591
```

## Fallback Behavior

**Important:** If the Firebase service account file is not found, the system will automatically fall back to local storage. This means:

- Files will be stored in `storage/app/public/lab_results/`
- URLs will be localhost URLs (like `http://localhost/storage/lab_results/...`)
- The job will complete successfully but with a warning in the logs
- You'll see warning messages like: "Firebase service account file not found. Falling back to local storage."

To get proper Firebase URLs, you must set up the service account file as described above.

## Troubleshooting

### Common Issues:

1. **Service Account File Not Found**
   - Ensure the service account JSON file is placed at `storage/app/firebase-service-account.json`
   - Check file permissions
   - The system will fall back to local storage if not found

2. **Upload Fails**
   - Check Firebase Storage rules
   - Verify project ID and bucket name in config
   - Check Laravel logs for detailed error messages

3. **Wrong URL Format**
   - If you see localhost URLs, it means Firebase service account is not set up
   - Set up the service account file to get proper Firebase URLs
   - Clear any cached config: `php artisan config:clear`

### Logs
Check the application logs for Firebase upload status:
```bash
grep "Firebase" storage/logs/laravel.log
```
