# **WebApp POS (point of sales) - Laravel + Vue 3**

### **Common Issues & Solutions**
1. **Stock Concurrency**: Gunakan database transactions dengan row-level locking
2. **Performance**: Implementasikan caching untuk product catalog dan dashboard
3. **PDF Reports**: Gunakan library seperti DomPDF atau Laravel Snappy
4. **Barcode Scanner**: Listen untuk 'keydown' event dengan delay untuk barcode scanner
5. **Offline Mode**: Gunakan Service Workers dan IndexedDB untuk offline capability

### **Security Checklist**
- [ ] Enable HTTPS (Let's Encrypt)
- [ ] Set proper CORS policies
- [ ] Implement rate limiting
- [ ] Regular security updates
- [ ] Database backup schedule
- [ ] Audit logging for critical actions
- [ ] CSRF protection for web routes

### **Performance Optimization**
- Database indexing on frequently queried columns
- Implement Redis caching for product catalog
- Use eager loading to prevent N+1 queries
- Queue heavy operations (report generation, bulk import)
- Implement pagination for all list endpoints

---

### **Installation**
```bash
cd /var/www/pos-app/backend

# Build and start containers
docker compose build 
docker compose up -d

# Generate application key
docker compose exec backend php artisan key:generate

# Run migrations
docker compose exec backend php artisan migrate --seed

# Check logs
docker compose logs backend

# Access the application
# Laravel: http://localhost:8000
# Frontend: http://localhost:5173
# Nginx: http://localhost (if configured correctly)
# "Admin login: admin@pos.com / password123"

cd /var/www/pos-app/frontend
npm install
npm run build

sudo systemctl restart nginx
sudo systemctl restart php8.4-fpm
```

---

### **Postman Collection Variables**
```json
{
  "variables": [
    {
      "key": "base_url",
      "value": "http://localhost:8000",
      "type": "string"
    },
    {
      "key": "token",
      "value": "",
      "type": "string"
    }
  ]
}
```

---

- Base URL: http://localhost:8000/api
- Available Endpoints:
- GET /api/health - Check API status
- POST /api/login - Login user
- GET /api/user - Get user info (auth required)
- GET /api/products - List all products
- GET /api/products/search?q=keyword - Search products
- GET /api/cart - View cart
- POST /api/cart/items - Add to cart
- POST /api/sales - Create sale
- GET /api/sales - List sales
- POST /api/logout - Logout

---

Aplikasi POS ini telah memiliki fitur:
- ✅ Multi-user dengan RBAC
- ✅ Multi-branch support
- ✅ Real-time stock management
- ✅ Penjualan kasir (POS)
- ✅ Manajemen hutang-piutang
- ✅ Laporan dan analytics
- ✅ Docker containerization
- ✅ CI/CD pipeline

**Untuk pengembangan selanjutnya:**
1. Implementasi GraphQL untuk reporting
2. Mobile app untuk sales & kurir
3. Integration with accounting software
4. Advanced analytics dashboard
5. E-commerce integration
