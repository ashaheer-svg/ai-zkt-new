# 📱 FAMS Mobile API - Developer Deployment Guide

This document provides the technical specifications required to build the mobile application for the Zakath Financial Management System.

## 1. Authentication

The API uses **Bearer Token** authentication. Tokens are generated via the Web Dashboard and shared with the device via a QR code.

- **Header**: `Authorization: Bearer <secure_token>`
- **Deactivation**: If the user is deactivated in the web panel, the API will return `401 Unauthorized` regardless of token expiry.
- **Expiry**: Tokens expire after 365 days.

## 2. QR Code Pairing Format

The pairing QR code contains a JSON string with the following structure:
```json
{
  "api_url": "https://your-domain.com/fams/index.php",
  "token": "64_character_hex_token"
}
```

## 3. API Endpoints

All endpoints use `GET` for retrieval and `POST` for submission. The base URL is provided in the QR code.

### A. Fetch Document Types
Returns a list of active document types defined by the administrator.
- **Endpoint**: `?page=api.document-types`
- **Method**: `GET`
- **Response Sample**:
```json
[
  {"id": 1, "name": "Application Form"},
  {"id": 2, "name": "Photo"},
  {"id": 3, "name": "ID Copy"}
]
```

### B. List Projects (Applications)
Returns a list of applications assigned to the authenticated user.
- **Endpoint**: `?page=api.projects`
- **Method**: `GET`
- **Response Sample**:
```json
[
  {
    "id": 45,
    "applicant_name": "John Doe",
    "status": "approved",
    "village_name": "West Village",
    "category_name": "Livelihood"
  }
]
```

### C. Upload Document/Image
- **Endpoint**: `?page=api.upload`
- **Method**: `POST`
- **Content-Type**: `multipart/form-data`
- **Parameters**:
    - `file`: The image/file binary.
    - `application_id`: Integer ID of the project.
    - `doc_type`: String name of the type (selected from endpoint A).
    - `doc_language`: String (e.g., "English", "Sinhala", "Tamil").
    - `description`: (Optional) String notes.
- **Success Response**: `{"success": true, "message": "Image uploaded successfully"}`

## 4. Error Codes

- `401 Unauthorized`: Token invalid, expired, or user deactivated.
- `403 Forbidden`: User does not have permission to access the specific village or project.
- `400 Bad Request`: Missing parameters or invalid file.
- `500 Internal Server Error`: Server-side failure or upload error.
