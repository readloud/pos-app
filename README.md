# Aplikasi POS (web) lengkap dengan fitur penjualan, pembelian, manajemen stok, hutang-piutang, laporan, dan multi-user (admin, cabang, sales, kurir).

## Asumsi singkat (diambil sebagai default)
- Multi-tenant per cabang (data cabang terpisah dalam 1 DB).
- Real-time stok konsisten antar transaksi (optimistik/lock DB).
- Target deployment: cloud (DigitalOcean / AWS / Hetzner).
- Bahasa utama: Indonesia.

## Tech stack rekomendasi
- Backend: Laravel (PHP) atau Node.js (NestJS). (pilih salah satu; contoh di sini menggunakan Laravel)
- Frontend: Vue 3 + Vite + Pinia + Vue Router
- Database: PostgreSQL
- Caching/Queue: Redis
- Real-time: Laravel Echo + Pusher / WebSocket (socket.io)
- Storage: S3-compatible (DigitalOcean Spaces / AWS S3)
- Container & CI: Docker + GitHub Actions / GitLab CI
- Auth: JWT + refresh token untuk API, session untuk web admin
- Payment (opsional): midtrans / stripe

## Arsitektur & komponen utama
- API RESTful (JSON) + GraphQL opsional untuk reporting.
- SPA frontend (Dashboard admin, kasir, manajemen).
- Worker queue untuk proses berat (laporan bulk, import/export, sinkronisasi).
- Microservices optional: billing / reporting jika skala besar.

## Peran & hak akses (RBAC)
- Admin (full): konfigurasi, manajemen user, laporan global.
- Cabang Manager: lihat & kelola transaksi cabang sendiri, stok lokal.
- Sales: input penjualan (kunjungan), lihat daftar pelanggan/utang.
- Kasir: transaksi penjualan tunai/kartu, cetak nota.
- Kurir: lihat order pengiriman, update status pengiriman.
- Role-based permissions tabel: roles, permissions, role_permission, user_role.

## Entitas utama & skema ringkas (Postgres)
- users (id, name, email, password_hash, role_id, branch_id, last_login)
- branches (id, name, address, settings)
- products (id, sku, name, barcode, price_buy, price_sell, uom, tax, active)
- product_stock (id, product_id, branch_id, qty_on_hand, reserved)
- suppliers, customers (profil, contact, credit_limit)
- purchases (id, supplier_id, branch_id, total, status)
- purchase_items (purchase_id, product_id, qty, price, subtotal)
- sales (id, invoice_no, customer_id, branch_id, user_id, total, payment_status, delivery_status)
- sale_items (sale_id, product_id, qty, price, discount, subtotal)
- payments (id, ref_type, ref_id, amount, method, date)
- receivables (id, customer_id, sale_id, amount_due, due_date, status)
- payables (id, supplier_id, purchase_id, amount, due_date, status)
- stock_mutations (id, product_id, branch_id, qty, type [in/out/adjust], ref)
- audit_logs, notifications, settings, sessions

## Fitur fungsional per modul
- Penjualan (Kasir & Online)
  - POS mode (barcode scanning, quick keys), invoice/receipt printing (thermal), multiple payment split, discount per item/order, tax handling.
  - Offline caching & sync (opsional) untuk kasir loss-tolerant.
- Pembelian
  - Buat PO, terima barang, adjust stok otomatis, upload invoice, handle return.
- Manajemen Stok
  - Stock on-hand per cabang, transfer antar cabang, reserved/committed stock, stock opname (audit), minimal stock alert.
- Hutang & Piutang
  - Kredit pelanggan, tenor/due date, partial payments, aging report, supplier payables with payment scheduling.
- Laporan & Analytics
  - Laba rugi per periode, penjualan harian/mingguan/bulanan, top produk, stok kritis, aging piutang/hutang, laporan per cabang.
  - Export CSV/PDF, schedule email report.
- Multi-user & Multi-branch
  - Scoping data per branch, pusat (HQ) view aggregat, logging aktivitas, role-based UI.
- Integrasi & Ekspor
  - Export ke accounting (CSV), API untuk POS mobile, webhook untuk event (sale.created).
- Notifikasi & Pengiriman
  - Workflow order -> picking -> packing -> kurir -> delivered; notifikasi via email/SMS/WhatsApp (provider).
- Keamanan
  - Audit trail, 2FA (opsional), rate limit, input validation, prepared statements, secure file upload, RBAC.

## API endpoint contoh (ringkas)
- Auth: POST /api/login, POST /api/refresh, POST /api/logout
- Products: GET /api/products, POST /api/products, PUT /api/products/:id
- Stock: GET /api/branches/:id/stock, POST /api/stock/transfer
- Sales: POST /api/sales, GET /api/sales/:id, POST /api/sales/:id/payment
- Purchases: POST /api/purchases, PUT /api/purchases/:id/receive
- Reports: GET /api/reports/sales?from=&to=&branch=
- Receivables: GET /api/receivables, POST /api/receivables/:id/payment

## UI screens (prioritas)
1. Login / Multi-branch selector
2. Dashboard (ringkasan penjualan, stok, hutang/piutang)
3. POS/Kasir (fast checkout, print)
4. Sale details & history
5. Purchase & GRN (goods received)
6. Inventory & stock transfer
7. Customers & Suppliers (credit terms)
8. Reports (filters & export)
9. User & Role management
10. Settings (tax, invoice template, branch config)

## Alur transaksi penting (contoh penjualan)
1. Kasir scan barcode -> pilih qty -> add ke cart.
2. System cek stock_on_hand (optimistik lock/DB transaction).
3. Pilih payment method(s) -> buat payment record, update sale.status.
4. Kurangi stock (stock_mutations) dan catat audit_log.
5. Cetak nota & kirim notifikasi jika delivery.

## Konsistensi stok & concurrency
- Gunakan DB transaction + row-level locking (SELECT ... FOR UPDATE) atau operasi increment/decrement atomik.
- Untuk performa tinggi: event-sourcing atau materialized views untuk read-heavy queries.

## Deployment & Infrastruktur
- Docker Compose untuk dev; Kubernetes (k3s / EKS) untuk produksi skala.
- Backup DB nightly, wal archiving (Postgres), S3 snapshot.
- Monitoring: Prometheus + Grafana, log central (ELK / Loki).
- TLS (Let's Encrypt), WAF basic, automated migrations.

## Testing & QA
- Unit tests (model, service), integration tests (API), E2E (Playwright / Cypress) untuk flows POS dan pembayaran.
- Load test (k6) untuk memvalidasi throughput kasir.

## Estimasi waktu pengembangan (MVP)
- Discovery + spesifikasi: 1–2 minggu
- Backend core (auth, products, stock, sales basic): 3–4 minggu
- Frontend POS + basic UI: 2–3 minggu
- Purchases, transfers, hutang/piutang, laporan dasar: 3–4 minggu
- Testing, hardening, deploy: 1–2 minggu
Total MVP: ~10–15 minggu (1–3 developer + 1 QA) — estimasi tergantung kompleksitas dan integrasi pihak ketiga.

## Prioritas fitur untuk MVP (rekomendasi)
1. Penjualan kasir (offline optional), cetak nota.
2. Produk & stok per cabang, stock mutation on sale.
3. Pembayaran & receipt.
4. Pembelian dasar (GRN) & update stok.
5. Laporan ringkas (penjualan harian, stok kritis).
6. Multi-user & RBAC minimal (Admin, Kasir, Manager).

## Checklist keamanan & compliance singkat
- Hash password (argon2/bcrypt), TLS everywhere.
- Input sanitization & CSRF protection (web).
- Least privilege for DB credentials.
- Regular backups + test restore.
- Sediakan template repo (Laravel + Vue) dengan fitur POS dasar.


## Deliverable dalam repo
- Backend (Laravel)
  - Auth API (Laravel Sanctum) + role-based middleware (admin, branch, sales, courier)
  - Models & Migrations: users, roles, branches, products, product_stocks, sales, sale_items, purchases, purchase_items, payments, receivables, payables, stock_mutations, audit_logs
  - Controllers & Services: ProductController, StockController, SaleController, PurchaseController, PaymentController, ReportController
  - Events/Listeners untuk stock mutation & notifications
  - Queue config (Redis), job example for report export
  - API Resources (JSON) + Form Requests validation
  - Policy/Permission checks and seeders (roles, sample admin user)
  - OpenAPI (basic) and Postman collection
- Frontend (Vue 3 + Vite)
  - Auth flow (login, token refresh via Sanctum)
  - POS cashier screen: barcode scan, cart, multi-payment split, print receipt (thermal via browser print or WebUSB stub)
  - Product list, stock per branch, stock transfer UI
  - Purchase/GRN screens
  - Receivables/Payables screens with aging
  - Role-based menus + multi-branch selector
  - Pinia store, Vue Router, components library (TailwindCSS + Headless UI or Vuetify option)
- Dev tooling
  - Docker Compose with services: app (PHP), node, postgres, redis, queue worker, nginx
  - GitHub Actions CI (lint, test, build)
  - README with setup & seed commands, env examples, migration steps
  - Example seed data (products, branches, users)

## Repo structure (top-level)
- backend/ (Laravel app)
  - app/, routes/, database/migrations/, database/seeders/, tests/
  - docker/, docker-compose.yml
- frontend/ (Vue 3)
  - src/components/, src/pages/, src/stores/, src/api/
- infra/
  - k8s/ or docker-compose.prod.yml, nginx config
- docs/
  - API.md, ERD.png, user-flows.md, deployment.md

## Quick start (local, Docker)
1. git clone <repo>
2. cd repo && docker compose up -d
3. backend: composer install; cp .env.example .env; php artisan key:generate; php artisan migrate --seed
4. frontend: cd frontend; npm install; npm run dev
5. Login: use seeded admin (admin@local / password: secret) and create branch/users.

1. cp .env.example .env — sesuaikan DB
2. docker compose up -d
3. docker exec -it <app> bash
4. composer install
5. php artisan key:generate
6. php artisan migrate --seed
7. php artisan serve --host=0.0.0.0 --port=9000

1. cd frontend
2. npm install
3. npm run dev
4. Open http://localhost:5173

Notes:
1) Import openapi.yaml ke Swagger UI / Redoc / OpenAPI Generator.  
2) Import postman_collection.json ke Postman.  
3) Di Postman, jalankan request "Login" lalu Postman test script akan menyimpan access_token ke variable "token" (atau set manual ke {{token}}) — setelah itu panggil endpoints yang memerlukan auth.
