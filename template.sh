#!/usr/bin/env bash
#Pchmod +x template.sh
set -e
REPO_DIR="pos-template"
echo "Creating repo in ./${REPO_DIR}"
rm -rf "${REPO_DIR}"
mkdir -p "${REPO_DIR}"
cd "${REPO_DIR}"

git init

# backend
mkdir -p backend/{app/Models,app/Http/Controllers,app/Http/Middleware,database/migrations,database/seeders,routes,docker}
cat > backend/composer.json <<'PHPJSON'
{
  "name": "pos-template/backend",
  "require": {
    "php": "^8.1",
    "laravel/framework": "^10.0",
    "laravel/sanctum": "^3.3",
    "guzzlehttp/guzzle": "^7.0"
  },
  "autoload": {
    "psr-4": {"App\\": "app/"}
  }
}
PHPJSON

cat > backend/.env.example <<'ENV'
APP_NAME=POS
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pos
DB_USERNAME=pos
DB_PASSWORD=pos

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
ENV

cat > backend/docker-compose.yml <<'DC'
version: "3.8"
services:
  app:
    build: ./docker
    volumes: ./backend:/var/www/html
    ports: ["9000:9000"]
    depends_on: ["db","redis"]
  db:
    image: postgres:15
    environment:
      POSTGRES_DB: pos
      POSTGRES_USER: pos
      POSTGRES_PASSWORD: pos
    volumes: db-data:/var/lib/postgresql/data
  redis:
    image: redis:7
volumes:
  db-data:
DC

cat > backend/routes/api.php <<'PHP'
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;

Route::post('login',[AuthController::class,'login']);
Route::middleware('auth:sanctum')->group(function(){
  Route::get('user', fn(Request $r)=> $r->user());
  Route::post('logout',[AuthController::class,'logout']);
  Route::apiResource('products', ProductController::class);
  Route::post('sales', [SaleController::class,'store']);
  Route::get('reports/sales', [SaleController::class,'report']);
});
PHP

cat > backend/app/Models/Product.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model {
  protected $fillable = ['sku','name','price_buy','price_sell','uom','tax','active'];
  public function stocks(){ return $this->hasMany(ProductStock::class); }
}
PHP

cat > backend/app/Models/ProductStock.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductStock extends Model {
  protected $fillable = ['product_id','branch_id','qty_on_hand','reserved'];
  public function product(){ return $this->belongsTo(Product::class); }
}
PHP

cat > backend/app/Http/Controllers/AuthController.php <<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
  public function login(Request $r){
    $r->validate(['email'=>'required|email','password'=>'required']);
    $user = User::where('email',$r->email)->first();
    if(!$user || !Hash::check($r->password,$user->password)) return response()->json(['message'=>'Unauthorized'],401);
    $token = $user->createToken('api-token')->plainTextToken;
    return response()->json(['user'=>$user,'token'=>$token]);
  }
  public function logout(Request $r){ $r->user()->currentAccessToken()->delete(); return response()->noContent(); }
}
PHP

cat > backend/app/Http/Controllers/ProductController.php <<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Product;
class ProductController extends Controller {
  public function index(Request $r){
    $q = Product::query();
    if($r->has('q')) $q->where('name','ilike','%'.$r->q.'%');
    return $q->paginate(20);
  }
  public function store(Request $r){
    $data = $r->validate(['sku'=>'nullable|string','name'=>'required','price_buy'=>'numeric','price_sell'=>'numeric']);
    $p = Product::create($data);
    return response()->json($p,201);
  }
}
PHP

cat > backend/app/Http/Controllers/SaleController.php <<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
class SaleController extends Controller {
  public function store(Request $r){
    $r->validate(['branch_id'=>'required|integer','items'=>'required|array']);
    return DB::transaction(function() use($r){
      $sale = Sale::create(['invoice_no'=>'INV'.time(),'branch_id'=>$r->branch_id,'user_id'=>$r->user()->id,'total'=>0,'payment_status'=>'pending']);
      $total = 0;
      foreach($r->items as $it){
        $productId = $it['product_id']; $qty = (int)$it['qty']; $price = $it['price'];
        $stock = ProductStock::where('product_id',$productId)->where('branch_id',$r->branch_id)->lockForUpdate()->firstOrFail();
        if($stock->qty_on_hand < $qty) throw new \Exception('Insufficient stock for product '.$productId);
        $stock->qty_on_hand -= $qty; $stock->save();
        $sub = $qty * $price; $total += $sub;
        SaleItem::create(['sale_id'=>$sale->id,'product_id'=>$productId,'qty'=>$qty,'price'=>$price,'subtotal'=>$sub]);
      }
      $sale->total = $total; $sale->payment_status = 'paid'; $sale->save();
      return response()->json($sale->load('items'),201);
    });
  }
  public function report(Request $r){
    $from = $r->query('from'); $to = $r->query('to');
    $q = Sale::query();
    if($from) $q->whereDate('created_at','>=',$from);
    if($to) $q->whereDate('created_at','<=',$to);
    return $q->with('items')->get();
  }
}
PHP

cat > backend/database/migrations/2026_06_01_000003_create_products_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateProductsTable extends Migration {
  public function up(){
    Schema::create('products', function(Blueprint $table){
      $table->id();
      $table->string('sku')->nullable()->unique();
      $table->string('name');
      $table->decimal('price_buy',12,2)->default(0);
      $table->decimal('price_sell',12,2)->default(0);
      $table->string('uom')->nullable();
      $table->boolean('active')->default(true);
      $table->timestamps();
    });
  }
  public function down(){ Schema::dropIfExists('products'); }
}
PHP

cat > backend/database/seeders/ProductSeeder.php <<'PHP'
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Branch;
class ProductSeeder extends Seeder {
  public function run(){
    $b = Branch::first() ?: Branch::create(['name'=>'Main Branch','address'=>'']);
    $products = [
      ['sku'=>'P001','name'=>'Minyak Goreng 1L','price_buy'=>8000,'price_sell'=>10000],
      ['sku'=>'P002','name'=>'Beras 5kg','price_buy'=>40000,'price_sell'=>50000],
    ];
    foreach($products as $p){
      $prod = Product::create($p);
      ProductStock::create(['product_id'=>$prod->id,'branch_id'=>$b->id,'qty_on_hand'=>100,'reserved'=>0]);
    }
  }
}
PHP

cat > backend/README.md <<'MD'
Backend setup (dev):
1. cp .env.example .env
2. docker compose up -d
3. enter container: docker exec -it <app> bash
4. composer install
5. php artisan key:generate
6. php artisan migrate --seed
7. php artisan serve --host=0.0.0.0 --port=9000
MD

# frontend
mkdir -p frontend/src/{api,stores,pages,components}
cat > frontend/package.json <<'JSON'
{
  "name":"pos-frontend",
  "private":true,
  "scripts":{"dev":"vite","build":"vite build","preview":"vite preview"},
  "dependencies":{
    "vue":"^3.3.0",
    "pinia":"^2.0.0",
    "axios":"^1.4.0",
    "vue-router":"^4.2.0",
    "tailwindcss":"^3.4.0"
  }
}
JSON

cat > frontend/src/api/api.js <<'JS'
import axios from 'axios';
const api = axios.create({ baseURL: 'http://localhost:9000/api', withCredentials: true });
export default api;
JS

cat > frontend/src/api/auth.js <<'JS'
import api from './api';
export async function login(email,password){ return api.post('/login',{email,password}); }
export async function logout(){ return api.post('/logout'); }
JS

cat > frontend/src/stores/cart.js <<'JS'
import { defineStore } from 'pinia';
export const useCart = defineStore('cart',{ state:()=>({items:[]}), actions:{ add(item){ this.items.push(item); }, clear(){ this.items=[]; } } });
JS

cat > frontend/src/pages/POS.vue <<'VUE'
<template>
  <div class="p-4">
    <h2>POS</h2>
    <div class="grid grid-cols-3 gap-4">
      <div>
        <input v-model="q" placeholder="Cari produk" @input="search" class="border p-2 w-full"/>
        <div v-for="p in products" :key="p.id" class="p-2 border mt-2" @click="addToCart(p)">{{p.name}} — {{p.price_sell}}</div>
      </div>
      <div class="col-span-2">
        <h3>Cart</h3>
        <div v-for="(it,i) in cart.items" :key="i" class="flex justify-between p-2 border">
          <div>{{it.name}} x{{it.qty}}</div><div>{{it.price*it.qty}}</div>
        </div>
        <div class="mt-4">
          <button @click="checkout" class="bg-green-500 text-white px-4 py-2">Checkout</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { ref } from 'vue'; import { useCart } from '../stores/cart'; import api from '../api/api';
export default { setup(){
  const q = ref(''), products = ref([]), cart = useCart();
  async function search(){ const res = await api.get('/products',{params:{q:q.value}}); products.value = res.data.data || res.data; }
  function addToCart(p){ cart.add({product_id:p.id,name:p.name,price:p.price_sell,qty:1}); }
  async function checkout(){
    const items = cart.items.map(i=>({product_id:i.product_id,qty:i.qty,price:i.price}));
    await api.post('/sales',{branch_id:1,items});
    cart.clear(); alert('Sale created');
  }
  search();
  return {q,products,cart,search,addToCart,checkout};
}}
</script>
VUE

cat > frontend/README.md <<'MD'
Frontend setup:
1. cd frontend
2. npm install
3. npm run dev
4. Open http://localhost:5173
MD

# infra
mkdir -p infra/nginx
cat > infra/nginx/default.conf <<'NGINX'
server {
  listen 80;
  server_name _;
  location / {
    try_files $uri $uri/ /index.html;
  }
  location /api/ {
    proxy_pass http://app:9000;
  }
}
NGINX

# docs
mkdir docs
cat > docs/API.md <<'MD'
Basic API endpoints:
POST /api/login {email,password}
GET  /api/products
POST /api/products {...}
POST /api/sales {branch_id, items:[{product_id,qty,price}]}
MD


