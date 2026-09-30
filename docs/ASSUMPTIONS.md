# Assumptions & Design Decisions
**Project:** Smart Real-Time Monitoring & Inspection App (SIH26095, Team CHAKRAVYUH, MoSJE)

## 1. Database Configuration
- **Default Database:** SQLite (PDO SQLite) is configured as the zero-dependency, self-contained default for immediate portability and testing without external service requirements.
- **MySQL Compatibility:** A single `.env` setting (`DB_CONNECTION=mysql` vs `DB_CONNECTION=sqlite`) toggles between SQLite and MySQL/MariaDB with an identical schema and ANSI SQL prepared statements.
- **Database File:** When using SQLite, the database file resides at `backend/storage/database.sqlite`.

## 2. Environment & Execution
- **OS & Runtime:** Windows 10/11 environment with portable PHP 8.3 (with `pdo_sqlite`, `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `gd`, `openssl`) and OpenJDK 17.
- **Backend Server:** Built-in PHP development server (`php -S 127.0.0.1:8000 router.php`) handles REST API requests, routing, CORS, and static media serving.

## 3. SMS Gateway & OTP Verification
- **Provider Interface:** `SmsProvider` interface implemented with `DemoSmsProvider`.
- **Demo Mode:** When `APP_ENV=demo`, the generated OTP is logged and returned in the JSON response under `demo_otp` for testing and evaluation.
- **Rate Limits & Expiry:** Maximum 5 OTP requests per hour per mobile number; OTPs expire after 5 minutes (300 seconds).

## 4. Payment Gateway & Escalation Fees
- **Provider Interface:** `PaymentProvider` interface implemented with `DemoPaymentProvider`.
- **Escalation Fee:** ₹400 base fee + configurable GST (default 18%, ₹72, total ₹472) per escalation level.
- **Refund Policy:** If higher authority upholds the complaint, an automated refund transaction is recorded. If rejected, the fee is retained.

## 5. Watermarking & Media Evidence
- **Image Watermarking:** Pixels burned client-side with translucent white text `for the people to the people` (~40% opacity, shadow) in bottom-right corner, and coordinate/datetime line in bottom-left corner.
- **Video Evidence:** Short clips (max 30s); thumbnail and mandatory first-frame still burned with the identical watermark and GPS telemetry.
- **Server Verification:** Server stamps `server_time`. If device capture time and server time differ by > 24 hours, `time_mismatch=true` is flagged.

## 6. Android Build & Verification
- **Language & Architecture:** Pure Java (no Kotlin), XML layouts, Material Components 3, minSdk 26, targetSdk 34.
- **Libraries:** Retrofit 2 + OkHttp 3, Room, WorkManager, CameraX, Google Play Services Fused Location, Glide.
- **Offline Sync:** Inspection and evidence queue saved locally in SQLite via Room; WorkManager handles automatic background synchronization with exponential backoff.
- **Verification Strategy:** Full Android project structure with Gradle wrapper; Java source compilation verified with JDK 17 `javac`; in addition, an interactive web preview (`preview/`) renders identical UI screens and makes live calls to the backend API.
