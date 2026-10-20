<?php
/**
 * Realistic Sample Business Data Seeder
 * Smart Business Management System
 * 
 * Safely seeds:
 * - 7 Product Categories
 * - 7 Suppliers (with safe demo contact details)
 * - 42 Products (with realistic ₹ purchase/selling prices, units & thresholds)
 * - 5 Demonstration Customers
 * - Complete Supply Chain Workflow:
 *     Supplier -> Stockiest (Purchases)
 *     Stockiest -> Distributor (Transfers)
 *     Distributor -> Retailer (Transfers)
 *     Retailer -> Customer (POS Sales & Payments)
 * - Real Stock Movement Audit Ledger
 * 
 * IDEMPOTENT: Safe to run multiple times without duplicating records or double-counting stock.
 */

require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================================\n";
echo "SMART BUSINESS - REALISTIC SAMPLE DATA SEEDER\n";
echo "========================================================\n\n";

// Disable autocommit for transactional safety where appropriate
mysqli_begin_transaction($conn);

try {
    // ---------------------------------------------------------
    // STEP 1: VERIFY CORE ROLES & USERS
    // ---------------------------------------------------------
    echo "1. Checking core roles and user accounts...\n";
    $users_map = [];
    $u_res = mysqli_query($conn, "SELECT u.id, u.user_id, r.role_name FROM users u JOIN roles r ON u.role_id = r.id");
    while ($u = mysqli_fetch_assoc($u_res)) {
        $users_map[$u['role_name']] = (int)$u['id'];
    }

    if (!isset($users_map['Admin'], $users_map['Stockiest'], $users_map['Distributor'], $users_map['Retailer'])) {
        throw new Exception("Core user accounts missing. Please ensure ADM001, STK001, DST001, RET001 are present.");
    }
    $admin_id = $users_map['Admin'];
    $stockiest_id = $users_map['Stockiest'];
    $distributor_id = $users_map['Distributor'];
    $retailer_id = $users_map['Retailer'];
    echo "   [OK] Admin ID: {$admin_id}, Stockiest ID: {$stockiest_id}, Distributor ID: {$distributor_id}, Retailer ID: {$retailer_id}\n\n";

    // ---------------------------------------------------------
    // STEP 2: SEED 7 PRODUCT CATEGORIES
    // ---------------------------------------------------------
    echo "2. Seeding 7 Product Categories...\n";
    $categories_data = [
        ['category_name' => 'Beverages', 'description' => 'Mineral water, packaged fruit juices, soft drinks, energy drinks, tea and coffee.'],
        ['category_name' => 'Grocery & Staples', 'description' => 'Daily kitchen grains, rice varieties, flours, pulses, cooking oils, salt and sugar.'],
        ['category_name' => 'Snacks & Packaged Foods', 'description' => 'Biscuits, crispy potato chips, instant noodles, chocolates, cookies and confectionery.'],
        ['category_name' => 'Personal Care', 'description' => 'Bathing soaps, herbal shampoos, toothpastes, toothbrushes, hair oils and hand washes.'],
        ['category_name' => 'Household & Cleaning', 'description' => 'Detergent powders, dishwashing gels, disinfectant floor and toilet cleaners, garbage bags.'],
        ['category_name' => 'Stationery', 'description' => 'Student notebooks, ball pens, pencil boxes, A4 copier paper reams, markers and staplers.'],
        ['category_name' => 'Dairy & Packaged Foods', 'description' => 'Pasteurized milk, fresh curd, butter, processed cheese, fresh bread and vacuum-packed paneer.']
    ];

    $category_id_map = [];
    $stmt_cat_chk = mysqli_prepare($conn, "SELECT id FROM categories WHERE category_name = ? LIMIT 1");
    $stmt_cat_ins = mysqli_prepare($conn, "INSERT INTO categories (category_name, description, status) VALUES (?, ?, 'Active')");

    foreach ($categories_data as $cat) {
        mysqli_stmt_bind_param($stmt_cat_chk, "s", $cat['category_name']);
        mysqli_stmt_execute($stmt_cat_chk);
        $res = mysqli_stmt_get_result($stmt_cat_chk);
        if ($row = mysqli_fetch_assoc($res)) {
            $cat_id = (int)$row['id'];
            echo "   [EXISTS] Category: '{$cat['category_name']}' (ID: {$cat_id})\n";
        } else {
            mysqli_stmt_bind_param($stmt_cat_ins, "ss", $cat['category_name'], $cat['description']);
            mysqli_stmt_execute($stmt_cat_ins);
            $cat_id = mysqli_insert_id($conn);
            echo "   [CREATED] Category: '{$cat['category_name']}' (ID: {$cat_id})\n";
        }
        $category_id_map[$cat['category_name']] = $cat_id;
    }
    echo "\n";

    // ---------------------------------------------------------
    // STEP 3: SEED 7 SUPPLIERS
    // ---------------------------------------------------------
    echo "3. Seeding 7 Sample Suppliers...\n";
    $suppliers_data = [
        [
            'supplier_id' => 'SUP001',
            'supplier_name' => 'Southern Beverages Supply',
            'company_name' => 'Southern Beverages Pvt Ltd',
            'phone' => '9840112345',
            'email' => 'contact@southernbev.demo.local',
            'address' => 'Plot 45, Demo Industrial Estate, Guindy, Chennai 600032',
            'category' => 'Beverages'
        ],
        [
            'supplier_id' => 'SUP002',
            'supplier_name' => 'Tamil Nadu Grocery Distributors',
            'company_name' => 'TN Grocery Wholesale Hub',
            'phone' => '9840223456',
            'email' => 'orders@tngrocery.demo.local',
            'address' => 'Shop 12B, Demo Wholesale Market, Koyambedu, Chennai 600107',
            'category' => 'Grocery & Staples'
        ],
        [
            'supplier_id' => 'SUP003',
            'supplier_name' => 'Fresh Pack Wholesale',
            'company_name' => 'Fresh Pack FMCG Supplies',
            'phone' => '9840334567',
            'email' => 'supply@freshpack.demo.local',
            'address' => 'B-8, Demo Trade Center, Ambattur Industrial Estate, Chennai 600058',
            'category' => 'Snacks & Packaged Foods'
        ],
        [
            'supplier_id' => 'SUP004',
            'supplier_name' => 'Daily Essentials Suppliers',
            'company_name' => 'Daily Essentials Health & Care Ltd',
            'phone' => '9840445678',
            'email' => 'sales@dailyessentials.demo.local',
            'address' => '22 Demo Commercial Hub, Usman Road, T. Nagar, Chennai 600017',
            'category' => 'Personal Care'
        ],
        [
            'supplier_id' => 'SUP005',
            'supplier_name' => 'CleanHome Wholesale',
            'company_name' => 'CleanHome Hygiene Products',
            'phone' => '9840556789',
            'email' => 'info@cleanhome.demo.local',
            'address' => 'Warehouse No. 4, Demo Logistics Park, Madhavaram, Chennai 600060',
            'category' => 'Household & Cleaning'
        ],
        [
            'supplier_id' => 'SUP006',
            'supplier_name' => 'Stationery World Distributors',
            'company_name' => 'Stationery World & Paper Mart',
            'phone' => '9840667890',
            'email' => 'orders@stationeryworld.demo.local',
            'address' => '71 Demo Paper Bazaar, Bunder Street, Parry\'s Corner, Chennai 600001',
            'category' => 'Stationery'
        ],
        [
            'supplier_id' => 'SUP007',
            'supplier_name' => 'Fresh Dairy Supply',
            'company_name' => 'Fresh Dairy Products Cooperative',
            'phone' => '9840778901',
            'email' => 'dispatch@freshdairy.demo.local',
            'address' => 'Dairy Complex Road, Demo Agro Zone, Madhavaram Milk Colony, Chennai 600051',
            'category' => 'Dairy & Packaged Foods'
        ]
    ];

    $supplier_id_map = [];
    $stmt_sup_chk = mysqli_prepare($conn, "SELECT id FROM suppliers WHERE supplier_id = ? LIMIT 1");
    $stmt_sup_ins = mysqli_prepare($conn, "INSERT INTO suppliers (supplier_id, supplier_name, company_name, phone, email, address, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");

    foreach ($suppliers_data as $sup) {
        mysqli_stmt_bind_param($stmt_sup_chk, "s", $sup['supplier_id']);
        mysqli_stmt_execute($stmt_sup_chk);
        $res = mysqli_stmt_get_result($stmt_sup_chk);
        if ($row = mysqli_fetch_assoc($res)) {
            $s_id = (int)$row['id'];
            echo "   [EXISTS] Supplier: '{$sup['supplier_name']}' ({$sup['supplier_id']} - ID: {$s_id})\n";
        } else {
            mysqli_stmt_bind_param($stmt_sup_ins, "ssssss", $sup['supplier_id'], $sup['supplier_name'], $sup['company_name'], $sup['phone'], $sup['email'], $sup['address']);
            mysqli_stmt_execute($stmt_sup_ins);
            $s_id = mysqli_insert_id($conn);
            echo "   [CREATED] Supplier: '{$sup['supplier_name']}' ({$sup['supplier_id']} - ID: {$s_id})\n";
        }
        $supplier_id_map[$sup['supplier_id']] = $s_id;
        $supplier_id_map[$sup['category']] = $s_id;
    }
    echo "\n";

    // ---------------------------------------------------------
    // STEP 4: SEED 5 SAMPLE CUSTOMERS
    // ---------------------------------------------------------
    echo "4. Seeding 5 Sample Customers (Retailer CRM)...\n";
    $customers_data = [
        [
            'customer_id' => 'CUST001',
            'customer_name' => 'Customer Demo 001',
            'phone' => '9123450001',
            'email' => 'customer001@demo.local',
            'address' => '12 Demo Street, Gandhi Nagar, Chennai 600020'
        ],
        [
            'customer_id' => 'CUST002',
            'customer_name' => 'Customer Demo 002',
            'phone' => '9123450002',
            'email' => 'customer002@demo.local',
            'address' => '45 Demo 2nd Cross, Anna Nagar, Chennai 600040'
        ],
        [
            'customer_id' => 'CUST003',
            'customer_name' => 'Customer Demo 003',
            'phone' => '9123450003',
            'email' => 'customer003@demo.local',
            'address' => '78 Demo Avenue, 4th Sector, KK Nagar, Chennai 600078'
        ],
        [
            'customer_id' => 'CUST004',
            'customer_name' => 'Customer Demo 004',
            'phone' => '9123450004',
            'email' => 'customer004@demo.local',
            'address' => '101 Demo Main Bazaar, Velachery, Chennai 600042'
        ],
        [
            'customer_id' => 'CUST005',
            'customer_name' => 'Customer Demo 005',
            'phone' => '9123450005',
            'email' => 'customer005@demo.local',
            'address' => '234 Demo Colony, GST Road, Tambaram, Chennai 600045'
        ]
    ];

    $customer_id_map = [];
    $stmt_cust_chk = mysqli_prepare($conn, "SELECT id FROM customers WHERE customer_id = ? LIMIT 1");
    $stmt_cust_ins = mysqli_prepare($conn, "INSERT INTO customers (customer_id, customer_name, phone, email, address, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");

    foreach ($customers_data as $cust) {
        mysqli_stmt_bind_param($stmt_cust_chk, "s", $cust['customer_id']);
        mysqli_stmt_execute($stmt_cust_chk);
        $res = mysqli_stmt_get_result($stmt_cust_chk);
        if ($row = mysqli_fetch_assoc($res)) {
            $c_id = (int)$row['id'];
            echo "   [EXISTS] Customer: '{$cust['customer_name']}' ({$cust['customer_id']} - ID: {$c_id})\n";
        } else {
            mysqli_stmt_bind_param($stmt_cust_ins, "sssssi", $cust['customer_id'], $cust['customer_name'], $cust['phone'], $cust['email'], $cust['address'], $retailer_id);
            mysqli_stmt_execute($stmt_cust_ins);
            $c_id = mysqli_insert_id($conn);
            echo "   [CREATED] Customer: '{$cust['customer_name']}' ({$cust['customer_id']} - ID: {$c_id})\n";
        }
        $customer_id_map[$cust['customer_id']] = $c_id;
    }
    echo "\n";

    // ---------------------------------------------------------
    // STEP 5: SEED 42 REALISTIC PRODUCTS
    // ---------------------------------------------------------
    echo "5. Seeding 42 Realistic Products across 7 Categories...\n";
    $products_data = [
        // Category 1: Beverages
        ['code' => 'PRD001', 'name' => 'Mineral Water 1L', 'cat' => 'Beverages', 'brand' => 'Aquafine', 'unit' => 'Bottle', 'cost' => 10.00, 'sell' => 15.00, 'min_stock' => 20],
        ['code' => 'PRD002', 'name' => 'Soft Drink 750ml', 'cat' => 'Beverages', 'brand' => 'ColaFizz', 'unit' => 'Bottle', 'cost' => 30.00, 'sell' => 40.00, 'min_stock' => 15],
        ['code' => 'PRD003', 'name' => 'Fruit Juice 1L', 'cat' => 'Beverages', 'brand' => 'RealFresh', 'unit' => 'Tetra Pack', 'cost' => 70.00, 'sell' => 95.00, 'min_stock' => 10],
        ['code' => 'PRD004', 'name' => 'Energy Drink 250ml', 'cat' => 'Beverages', 'brand' => 'VoltRush', 'unit' => 'Can', 'cost' => 85.00, 'sell' => 110.00, 'min_stock' => 10],
        ['code' => 'PRD005', 'name' => 'Tea Powder 250g', 'cat' => 'Beverages', 'brand' => 'RedChai', 'unit' => 'Pack', 'cost' => 65.00, 'sell' => 85.00, 'min_stock' => 15],
        ['code' => 'PRD006', 'name' => 'Coffee Powder 100g', 'cat' => 'Beverages', 'brand' => 'BruAroma', 'unit' => 'Jar', 'cost' => 120.00, 'sell' => 150.00, 'min_stock' => 10],

        // Category 2: Grocery & Staples
        ['code' => 'PRD007', 'name' => 'Rice 5kg', 'cat' => 'Grocery & Staples', 'brand' => 'Ponni Classic', 'unit' => 'Bag', 'cost' => 250.00, 'sell' => 300.00, 'min_stock' => 10],
        ['code' => 'PRD008', 'name' => 'Wheat Flour 1kg', 'cat' => 'Grocery & Staples', 'brand' => 'Aashirvad', 'unit' => 'Pack', 'cost' => 35.00, 'sell' => 45.00, 'min_stock' => 15],
        ['code' => 'PRD009', 'name' => 'Sugar 1kg', 'cat' => 'Grocery & Staples', 'brand' => 'SweetCane', 'unit' => 'Pack', 'cost' => 38.00, 'sell' => 48.00, 'min_stock' => 20],
        ['code' => 'PRD010', 'name' => 'Toor Dal 1kg', 'cat' => 'Grocery & Staples', 'brand' => 'FarmFresh', 'unit' => 'Pack', 'cost' => 130.00, 'sell' => 160.00, 'min_stock' => 10],
        ['code' => 'PRD011', 'name' => 'Cooking Oil 1L', 'cat' => 'Grocery & Staples', 'brand' => 'SunPure', 'unit' => 'Pouch', 'cost' => 115.00, 'sell' => 140.00, 'min_stock' => 12],
        ['code' => 'PRD012', 'name' => 'Salt 1kg', 'cat' => 'Grocery & Staples', 'brand' => 'Tata Pure', 'unit' => 'Pack', 'cost' => 18.00, 'sell' => 25.00, 'min_stock' => 25],

        // Category 3: Snacks & Packaged Foods
        ['code' => 'PRD013', 'name' => 'Biscuits 200g', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'Parle Gold', 'unit' => 'Pack', 'cost' => 22.00, 'sell' => 30.00, 'min_stock' => 20],
        ['code' => 'PRD014', 'name' => 'Potato Chips 100g', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'CrunchyLays', 'unit' => 'Pouch', 'cost' => 25.00, 'sell' => 35.00, 'min_stock' => 20],
        ['code' => 'PRD015', 'name' => 'Instant Noodles 70g', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'Maggi Quick', 'unit' => 'Pack', 'cost' => 10.00, 'sell' => 14.00, 'min_stock' => 30],
        ['code' => 'PRD016', 'name' => 'Chocolate Bar', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'Cadbury Silk', 'unit' => 'Bar', 'cost' => 35.00, 'sell' => 45.00, 'min_stock' => 25],
        ['code' => 'PRD017', 'name' => 'Cookies 250g', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'GoodDay Delight', 'unit' => 'Box', 'cost' => 45.00, 'sell' => 60.00, 'min_stock' => 15],
        ['code' => 'PRD018', 'name' => 'Packaged Cake', 'cat' => 'Snacks & Packaged Foods', 'brand' => 'Britannia Treat', 'unit' => 'Piece', 'cost' => 20.00, 'sell' => 28.00, 'min_stock' => 15],

        // Category 4: Personal Care
        ['code' => 'PRD019', 'name' => 'Bathing Soap 100g', 'cat' => 'Personal Care', 'brand' => 'Dettol Fresh', 'unit' => 'Piece', 'cost' => 25.00, 'sell' => 35.00, 'min_stock' => 25],
        ['code' => 'PRD020', 'name' => 'Shampoo 180ml', 'cat' => 'Personal Care', 'brand' => 'Clinic Plus', 'unit' => 'Bottle', 'cost' => 110.00, 'sell' => 145.00, 'min_stock' => 12],
        ['code' => 'PRD021', 'name' => 'Toothpaste 150g', 'cat' => 'Personal Care', 'brand' => 'Colgate Max', 'unit' => 'Tube', 'cost' => 60.00, 'sell' => 80.00, 'min_stock' => 15],
        ['code' => 'PRD022', 'name' => 'Toothbrush', 'cat' => 'Personal Care', 'brand' => 'Oral-B Soft', 'unit' => 'Piece', 'cost' => 18.00, 'sell' => 30.00, 'min_stock' => 20],
        ['code' => 'PRD023', 'name' => 'Hair Oil 200ml', 'cat' => 'Personal Care', 'brand' => 'Parachute Coconut', 'unit' => 'Bottle', 'cost' => 75.00, 'sell' => 98.00, 'min_stock' => 10],
        ['code' => 'PRD024', 'name' => 'Hand Wash 250ml', 'cat' => 'Personal Care', 'brand' => 'Lifebuoy Shield', 'unit' => 'Dispenser', 'cost' => 65.00, 'sell' => 89.00, 'min_stock' => 12],

        // Category 5: Household & Cleaning
        ['code' => 'PRD025', 'name' => 'Detergent Powder 1kg', 'cat' => 'Household & Cleaning', 'brand' => 'Surf Excel', 'unit' => 'Pack', 'cost' => 85.00, 'sell' => 115.00, 'min_stock' => 15],
        ['code' => 'PRD026', 'name' => 'Dishwashing Liquid 500ml', 'cat' => 'Household & Cleaning', 'brand' => 'Vim Gel', 'unit' => 'Bottle', 'cost' => 65.00, 'sell' => 90.00, 'min_stock' => 15],
        ['code' => 'PRD027', 'name' => 'Floor Cleaner 1L', 'cat' => 'Household & Cleaning', 'brand' => 'Lizol Citrus', 'unit' => 'Bottle', 'cost' => 110.00, 'sell' => 145.00, 'min_stock' => 10],
        ['code' => 'PRD028', 'name' => 'Toilet Cleaner 500ml', 'cat' => 'Household & Cleaning', 'brand' => 'Harpic Power', 'unit' => 'Bottle', 'cost' => 70.00, 'sell' => 92.00, 'min_stock' => 12],
        ['code' => 'PRD029', 'name' => 'Garbage Bags Pack', 'cat' => 'Household & Cleaning', 'brand' => 'CleanTidy 30s', 'unit' => 'Roll', 'cost' => 45.00, 'sell' => 65.00, 'min_stock' => 10],
        ['code' => 'PRD030', 'name' => 'Cleaning Cloth', 'cat' => 'Household & Cleaning', 'brand' => 'Microfiber Soft', 'unit' => 'Piece', 'cost' => 25.00, 'sell' => 40.00, 'min_stock' => 15],

        // Category 6: Stationery
        ['code' => 'PRD031', 'name' => 'Notebook', 'cat' => 'Stationery', 'brand' => 'Classmate 180pg', 'unit' => 'Book', 'cost' => 30.00, 'sell' => 40.00, 'min_stock' => 20],
        ['code' => 'PRD032', 'name' => 'Ball Pen', 'cat' => 'Stationery', 'brand' => 'Reynolds 045', 'unit' => 'Piece', 'cost' => 5.00, 'sell' => 10.00, 'min_stock' => 50],
        ['code' => 'PRD033', 'name' => 'Pencil Pack', 'cat' => 'Stationery', 'brand' => 'Apsara Platinum', 'unit' => 'Box', 'cost' => 25.00, 'sell' => 35.00, 'min_stock' => 20],
        ['code' => 'PRD034', 'name' => 'A4 Paper Ream', 'cat' => 'Stationery', 'brand' => 'JK Copier 500s', 'unit' => 'Ream', 'cost' => 220.00, 'sell' => 280.00, 'min_stock' => 5],
        ['code' => 'PRD035', 'name' => 'Marker Pen', 'cat' => 'Stationery', 'brand' => 'Camlin Whiteboard', 'unit' => 'Piece', 'cost' => 20.00, 'sell' => 30.00, 'min_stock' => 15],
        ['code' => 'PRD036', 'name' => 'Stapler', 'cat' => 'Stationery', 'brand' => 'Kangaro 10D', 'unit' => 'Piece', 'cost' => 45.00, 'sell' => 65.00, 'min_stock' => 10],

        // Category 7: Dairy & Packaged Foods
        ['code' => 'PRD037', 'name' => 'Milk 500ml', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'Aavin Toned', 'unit' => 'Pouch', 'cost' => 22.00, 'sell' => 27.00, 'min_stock' => 30],
        ['code' => 'PRD038', 'name' => 'Curd 500g', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'MilkyMist Cup', 'unit' => 'Cup', 'cost' => 30.00, 'sell' => 38.00, 'min_stock' => 20],
        ['code' => 'PRD039', 'name' => 'Butter 100g', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'Amul Salted', 'unit' => 'Pack', 'cost' => 45.00, 'sell' => 56.00, 'min_stock' => 15],
        ['code' => 'PRD040', 'name' => 'Cheese 200g', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'Amul Block', 'unit' => 'Block', 'cost' => 95.00, 'sell' => 120.00, 'min_stock' => 10],
        ['code' => 'PRD041', 'name' => 'Bread Loaf', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'Modern Milk Bread', 'unit' => 'Loaf', 'cost' => 28.00, 'sell' => 38.00, 'min_stock' => 20],
        ['code' => 'PRD042', 'name' => 'Packaged Paneer 200g', 'cat' => 'Dairy & Packaged Foods', 'brand' => 'MilkyMist Fresh', 'unit' => 'Pack', 'cost' => 75.00, 'sell' => 95.00, 'min_stock' => 12]
    ];

    $product_id_map = [];
    $stmt_prd_chk = mysqli_prepare($conn, "SELECT id FROM products WHERE product_id = ? LIMIT 1");
    $stmt_prd_ins = mysqli_prepare($conn, "INSERT INTO products (product_id, product_name, category_id, brand, unit, purchase_price, selling_price, minimum_stock_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')");

    foreach ($products_data as $p) {
        $c_id = $category_id_map[$p['cat']] ?? 1;
        mysqli_stmt_bind_param($stmt_prd_chk, "s", $p['code']);
        mysqli_stmt_execute($stmt_prd_chk);
        $res = mysqli_stmt_get_result($stmt_prd_chk);
        if ($row = mysqli_fetch_assoc($res)) {
            $p_db_id = (int)$row['id'];
        } else {
            mysqli_stmt_bind_param($stmt_prd_ins, "ssissddi", $p['code'], $p['name'], $c_id, $p['brand'], $p['unit'], $p['cost'], $p['sell'], $p['min_stock']);
            mysqli_stmt_execute($stmt_prd_ins);
            $p_db_id = mysqli_insert_id($conn);
            echo "   [CREATED] Product: {$p['code']} - {$p['name']} (Buy: ₹{$p['cost']}, Sell: ₹{$p['sell']})\n";
        }
        $product_id_map[$p['code']] = [
            'id' => $p_db_id,
            'name' => $p['name'],
            'cost' => $p['cost'],
            'sell' => $p['sell'],
            'min_stock' => $p['min_stock']
        ];
    }
    echo "   [OK] 42 Products verified in database.\n\n";

    // ---------------------------------------------------------
    // STEP 6: SEED REALISTIC SUPPLY CHAIN TRANSACTIONS
    // ---------------------------------------------------------
    echo "6. Seeding Supply Chain Workflow:\n";
    echo "   SUPPLIER -> STOCKIEST -> DISTRIBUTOR -> RETAILER -> CUSTOMER\n";

    // Helper functions for idempotent operations
    $update_stock = function($u_id, $p_id, $delta_qty, $type, $ref_id, $notes = '') use ($conn) {
        // Fetch or create stock row
        $stmt = mysqli_prepare($conn, "SELECT quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $u_id, $p_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $current_qty = (int)$row['quantity'];
            $new_qty = $current_qty + $delta_qty;
            if ($new_qty < 0) {
                throw new Exception("Stock cannot become negative for user {$u_id}, product {$p_id}");
            }
            $upd = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE user_id = ? AND product_id = ?");
            mysqli_stmt_bind_param($upd, "iii", $new_qty, $u_id, $p_id);
            mysqli_stmt_execute($upd);
        } else {
            $current_qty = 0;
            $new_qty = $delta_qty;
            if ($new_qty < 0) {
                throw new Exception("Initial stock cannot be negative for user {$u_id}, product {$p_id}");
            }
            $ins = mysqli_prepare($conn, "INSERT INTO stock (user_id, product_id, quantity) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, "iii", $u_id, $p_id, $new_qty);
            mysqli_stmt_execute($ins);
        }

        // Audit movement
        $mov = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $abs_qty = abs($delta_qty);
        mysqli_stmt_bind_param($mov, "iisiiiss", $u_id, $p_id, $type, $abs_qty, $current_qty, $new_qty, $ref_id, $notes);
        mysqli_stmt_execute($mov);
    };

    // A. STOCKIEST PURCHASES FROM SUPPLIERS
    $purchases_to_seed = [
        [
            'pur_id' => 'PUR20261001',
            'sup_code' => 'SUP001',
            'date' => '2026-10-01',
            'status' => 'Paid',
            'notes' => 'Central warehouse inward batch - Beverages',
            'items' => [
                ['code' => 'PRD001', 'qty' => 200], // Mineral Water
                ['code' => 'PRD002', 'qty' => 100], // Soft Drink
                ['code' => 'PRD003', 'qty' => 80],  // Fruit Juice
                ['code' => 'PRD004', 'qty' => 50],  // Energy Drink
                ['code' => 'PRD005', 'qty' => 80],  // Tea Powder
                ['code' => 'PRD006', 'qty' => 60],  // Coffee Powder
            ]
        ],
        [
            'pur_id' => 'PUR20261002',
            'sup_code' => 'SUP002',
            'date' => '2026-10-02',
            'status' => 'Paid',
            'notes' => 'Bulk procurement batch - Grocery & Staples',
            'items' => [
                ['code' => 'PRD007', 'qty' => 100], // Rice 5kg
                ['code' => 'PRD008', 'qty' => 120], // Wheat Flour
                ['code' => 'PRD009', 'qty' => 150], // Sugar
                ['code' => 'PRD010', 'qty' => 80],  // Toor Dal
                ['code' => 'PRD011', 'qty' => 100], // Cooking Oil
                ['code' => 'PRD012', 'qty' => 150], // Salt
            ]
        ],
        [
            'pur_id' => 'PUR20261003',
            'sup_code' => 'SUP003',
            'date' => '2026-10-03',
            'status' => 'Paid',
            'notes' => 'Wholesale snack shipment - Biscuits & Confectionery',
            'items' => [
                ['code' => 'PRD013', 'qty' => 120],
                ['code' => 'PRD014', 'qty' => 100],
                ['code' => 'PRD015', 'qty' => 150],
                ['code' => 'PRD016', 'qty' => 100],
                ['code' => 'PRD017', 'qty' => 80],
                ['code' => 'PRD018', 'qty' => 80],
            ]
        ],
        [
            'pur_id' => 'PUR20261004',
            'sup_code' => 'SUP004',
            'date' => '2026-10-04',
            'status' => 'Paid',
            'notes' => 'Personal hygiene and cosmetic supplies batch',
            'items' => [
                ['code' => 'PRD019', 'qty' => 120],
                ['code' => 'PRD020', 'qty' => 80],
                ['code' => 'PRD021', 'qty' => 100],
                ['code' => 'PRD022', 'qty' => 100],
                ['code' => 'PRD023', 'qty' => 60],
                ['code' => 'PRD024', 'qty' => 80],
            ]
        ],
        [
            'pur_id' => 'PUR20261005',
            'sup_code' => 'SUP005',
            'date' => '2026-10-05',
            'status' => 'Paid',
            'notes' => 'Household sanitation & cleaning products consignment',
            'items' => [
                ['code' => 'PRD025', 'qty' => 100],
                ['code' => 'PRD026', 'qty' => 80],
                ['code' => 'PRD027', 'qty' => 60],
                ['code' => 'PRD028', 'qty' => 80],
                ['code' => 'PRD029', 'qty' => 50],
                ['code' => 'PRD030', 'qty' => 80],
            ]
        ],
        [
            'pur_id' => 'PUR20261006',
            'sup_code' => 'SUP006',
            'date' => '2026-10-06',
            'status' => 'Paid',
            'notes' => 'Institutional stationery & copier paper stock',
            'items' => [
                ['code' => 'PRD031', 'qty' => 150],
                ['code' => 'PRD032', 'qty' => 300],
                ['code' => 'PRD033', 'qty' => 100],
                ['code' => 'PRD034', 'qty' => 30],
                ['code' => 'PRD035', 'qty' => 80],
                ['code' => 'PRD036', 'qty' => 50],
            ]
        ],
        [
            'pur_id' => 'PUR20261007',
            'sup_code' => 'SUP007',
            'date' => '2026-10-07',
            'status' => 'Partial',
            'paid_amount' => 15000.00,
            'notes' => 'Cold chain dairy shipment - partial advance paid',
            'items' => [
                ['code' => 'PRD037', 'qty' => 150],
                ['code' => 'PRD038', 'qty' => 100],
                ['code' => 'PRD039', 'qty' => 80],
                ['code' => 'PRD040', 'qty' => 50],
                ['code' => 'PRD041', 'qty' => 100],
                ['code' => 'PRD042', 'qty' => 60],
            ]
        ]
    ];

    echo "   A. Seeding Stockiest Purchases from Suppliers...\n";
    foreach ($purchases_to_seed as $pur) {
        $chk_p = mysqli_prepare($conn, "SELECT id FROM purchases WHERE purchase_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk_p, "s", $pur['pur_id']);
        mysqli_stmt_execute($chk_p);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_p))) {
            echo "      [EXISTS] Purchase {$pur['pur_id']} already recorded.\n";
            continue;
        }

        $s_id = $supplier_id_map[$pur['sup_code']];
        $total_amount = 0;
        foreach ($pur['items'] as $it) {
            $cost = $product_id_map[$it['code']]['cost'];
            $total_amount += ($cost * $it['qty']);
        }

        $ins_pur = mysqli_prepare($conn, "INSERT INTO purchases (purchase_id, supplier_id, user_id, purchase_date, total_amount, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_pur, "siisdss", $pur['pur_id'], $s_id, $stockiest_id, $pur['date'], $total_amount, $pur['status'], $pur['notes']);
        mysqli_stmt_execute($ins_pur);
        $pur_db_id = mysqli_insert_id($conn);

        // Insert items & credit stockiest warehouse
        $ins_item = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        foreach ($pur['items'] as $it) {
            $p_db_id = $product_id_map[$it['code']]['id'];
            $cost = $product_id_map[$it['code']]['cost'];
            $subtotal = $cost * $it['qty'];
            mysqli_stmt_bind_param($ins_item, "iiidd", $pur_db_id, $p_db_id, $it['qty'], $cost, $subtotal);
            mysqli_stmt_execute($ins_item);

            // Credit stockiest warehouse
            $update_stock($stockiest_id, $p_db_id, $it['qty'], 'Purchase', $pur['pur_id'], "Inward from {$pur['sup_code']}");
        }

        // Record payment
        $paid = ($pur['status'] === 'Paid') ? $total_amount : ($pur['paid_amount'] ?? $total_amount);
        $pay_id = 'PAY-P-' . substr($pur['pur_id'], 3);
        $ins_pay = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) VALUES (?, 'Purchase', ?, ?, ?, ?, 'Bank Transfer', 'Supplier settlement')");
        mysqli_stmt_bind_param($ins_pay, "ssids", $pay_id, $pur['pur_id'], $stockiest_id, $paid, $pur['date']);
        mysqli_stmt_execute($ins_pay);

        echo "      [RECORDED] Purchase {$pur['pur_id']}: ₹" . number_format($total_amount, 2) . " from {$pur['sup_code']}\n";
    }
    echo "\n";

    // B. STOCK TRANSFERS: STOCKIEST -> DISTRIBUTOR
    echo "   B. Seeding Stock Transfers: Stockiest -> Distributor...\n";
    $transfers_stk_to_dist = [
        ['trf_id' => 'TRF20261001', 'code' => 'PRD001', 'qty' => 80,  'date' => '2026-10-08', 'remarks' => 'Mineral Water dispatch to Metro Regional Distributor'],
        ['trf_id' => 'TRF20261002', 'code' => 'PRD002', 'qty' => 40,  'date' => '2026-10-08', 'remarks' => 'Soft Drinks supply dispatch'],
        ['trf_id' => 'TRF20261003', 'code' => 'PRD007', 'qty' => 40,  'date' => '2026-10-09', 'remarks' => 'Rice 5kg staple grain dispatch'],
        ['trf_id' => 'TRF20261004', 'code' => 'PRD008', 'qty' => 50,  'date' => '2026-10-09', 'remarks' => 'Wheat Flour package transfer'],
        ['trf_id' => 'TRF20261005', 'code' => 'PRD013', 'qty' => 50,  'date' => '2026-10-10', 'remarks' => 'Parle Gold Biscuits wholesale transfer'],
        ['trf_id' => 'TRF20261006', 'code' => 'PRD019', 'qty' => 50,  'date' => '2026-10-10', 'remarks' => 'Dettol Soap cases transfer'],
        ['trf_id' => 'TRF20261007', 'code' => 'PRD025', 'qty' => 40,  'date' => '2026-10-11', 'remarks' => 'Surf Excel Detergent distribution transfer'],
        ['trf_id' => 'TRF20261008', 'code' => 'PRD031', 'qty' => 60,  'date' => '2026-10-11', 'remarks' => 'Classmate Notebooks shipment'],
        ['trf_id' => 'TRF20261009', 'code' => 'PRD032', 'qty' => 100, 'date' => '2026-10-11', 'remarks' => 'Reynolds Ball Pens wholesale transfer'],
        ['trf_id' => 'TRF20261010', 'code' => 'PRD034', 'qty' => 15,  'date' => '2026-10-12', 'remarks' => 'JK Copier A4 Paper Reams dispatch'],
        ['trf_id' => 'TRF20261011', 'code' => 'PRD037', 'qty' => 60,  'date' => '2026-10-12', 'remarks' => 'Aavin Toned Milk pouches transfer'],
        ['trf_id' => 'TRF20261012', 'code' => 'PRD041', 'qty' => 40,  'date' => '2026-10-12', 'remarks' => 'Modern Bread crates dispatch'],
    ];

    foreach ($transfers_stk_to_dist as $t) {
        $chk_t = mysqli_prepare($conn, "SELECT id FROM stock_transfers WHERE transfer_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk_t, "s", $t['trf_id']);
        mysqli_stmt_execute($chk_t);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_t))) {
            echo "      [EXISTS] Transfer {$t['trf_id']} already recorded.\n";
            continue;
        }

        $p_db_id = $product_id_map[$t['code']]['id'];
        $ins_trf = mysqli_prepare($conn, "INSERT INTO stock_transfers (transfer_id, from_user_id, to_user_id, product_id, quantity, transfer_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, 'Completed', ?)");
        mysqli_stmt_bind_param($ins_trf, "siiiiss", $t['trf_id'], $stockiest_id, $distributor_id, $p_db_id, $t['qty'], $t['date'], $t['remarks']);
        mysqli_stmt_execute($ins_trf);

        // Debit Stockiest, Credit Distributor
        $update_stock($stockiest_id, $p_db_id, -$t['qty'], 'Transfer Out', $t['trf_id'], "Transfer to Distributor (DST001)");
        $update_stock($distributor_id, $p_db_id, $t['qty'], 'Transfer In', $t['trf_id'], "Received from Stockiest (STK001)");
        echo "      [COMPLETED] Transfer {$t['trf_id']}: {$t['qty']} units of {$t['code']} (Stockiest -> Distributor)\n";
    }
    echo "\n";

    // C. STOCK TRANSFERS: DISTRIBUTOR -> RETAILER
    echo "   C. Seeding Stock Transfers: Distributor -> Retailer...\n";
    $transfers_dist_to_ret = [
        ['trf_id' => 'TRF20261021', 'code' => 'PRD001', 'qty' => 40, 'date' => '2026-10-13', 'remarks' => 'Mineral water retail supply'],
        ['trf_id' => 'TRF20261022', 'code' => 'PRD002', 'qty' => 20, 'date' => '2026-10-13', 'remarks' => 'Soft drinks counter refill'],
        ['trf_id' => 'TRF20261023', 'code' => 'PRD007', 'qty' => 20, 'date' => '2026-10-14', 'remarks' => 'Rice 5kg retail packs'],
        ['trf_id' => 'TRF20261024', 'code' => 'PRD008', 'qty' => 25, 'date' => '2026-10-14', 'remarks' => 'Wheat flour consumer packs'],
        ['trf_id' => 'TRF20261025', 'code' => 'PRD013', 'qty' => 25, 'date' => '2026-10-15', 'remarks' => 'Biscuits shelf replenishment'],
        ['trf_id' => 'TRF20261026', 'code' => 'PRD019', 'qty' => 25, 'date' => '2026-10-15', 'remarks' => 'Dettol Soap retail dispatch'],
        ['trf_id' => 'TRF20261027', 'code' => 'PRD025', 'qty' => 20, 'date' => '2026-10-16', 'remarks' => 'Surf Excel detergent boxes'],
        ['trf_id' => 'TRF20261028', 'code' => 'PRD031', 'qty' => 30, 'date' => '2026-10-16', 'remarks' => 'Notebooks retail shelf stock'],
        ['trf_id' => 'TRF20261029', 'code' => 'PRD032', 'qty' => 50, 'date' => '2026-10-16', 'remarks' => 'Ball pens counter jars'],
        ['trf_id' => 'TRF20261030', 'code' => 'PRD034', 'qty' => 12, 'date' => '2026-10-17', 'remarks' => 'A4 Paper reams retail supply'],
        ['trf_id' => 'TRF20261031', 'code' => 'PRD037', 'qty' => 30, 'date' => '2026-10-17', 'remarks' => 'Daily fresh milk crate'],
        ['trf_id' => 'TRF20261032', 'code' => 'PRD041', 'qty' => 20, 'date' => '2026-10-17', 'remarks' => 'Fresh bread morning consignment'],
    ];

    foreach ($transfers_dist_to_ret as $t) {
        $chk_t = mysqli_prepare($conn, "SELECT id FROM stock_transfers WHERE transfer_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk_t, "s", $t['trf_id']);
        mysqli_stmt_execute($chk_t);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_t))) {
            echo "      [EXISTS] Transfer {$t['trf_id']} already recorded.\n";
            continue;
        }

        $p_db_id = $product_id_map[$t['code']]['id'];
        $ins_trf = mysqli_prepare($conn, "INSERT INTO stock_transfers (transfer_id, from_user_id, to_user_id, product_id, quantity, transfer_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, 'Completed', ?)");
        mysqli_stmt_bind_param($ins_trf, "siiiiss", $t['trf_id'], $distributor_id, $retailer_id, $p_db_id, $t['qty'], $t['date'], $t['remarks']);
        mysqli_stmt_execute($ins_trf);

        // Debit Distributor, Credit Retailer
        $update_stock($distributor_id, $p_db_id, -$t['qty'], 'Transfer Out', $t['trf_id'], "Transfer to Retailer (RET001)");
        $update_stock($retailer_id, $p_db_id, $t['qty'], 'Transfer In', $t['trf_id'], "Received from Distributor (DST001)");
        echo "      [COMPLETED] Transfer {$t['trf_id']}: {$t['qty']} units of {$t['code']} (Distributor -> Retailer)\n";
    }
    echo "\n";

    // D. RETAILER POS SALES & BILLING (RETAILER -> CUSTOMERS)
    echo "   D. Seeding Retailer POS Sales & Invoices...\n";
    $sales_to_seed = [
        [
            'sale_id' => 'INV20261001',
            'cust_code' => 'CUST001',
            'date' => '2026-10-18',
            'method' => 'Cash',
            'status' => 'Paid',
            'notes' => 'Store counter sale - Groceries & Milk',
            'payments' => [
                ['pay_id' => 'PAY-S-001', 'amount' => 204.00, 'date' => '2026-10-18', 'method' => 'Cash', 'notes' => 'Full cash payment at counter']
            ],
            'items' => [
                ['code' => 'PRD001', 'qty' => 4], // 4 x 15 = 60
                ['code' => 'PRD013', 'qty' => 3], // 3 x 30 = 90
                ['code' => 'PRD037', 'qty' => 2], // 2 x 27 = 54
            ]
        ],
        [
            'sale_id' => 'INV20261002',
            'cust_code' => 'CUST002',
            'date' => '2026-10-18',
            'method' => 'UPI',
            'status' => 'Paid',
            'notes' => 'Home delivery order paid via GooglePay QR',
            'payments' => [
                ['pay_id' => 'PAY-S-002', 'amount' => 390.00, 'date' => '2026-10-18', 'method' => 'UPI', 'notes' => 'UPI settlement confirmation ref #98347']
            ],
            'items' => [
                ['code' => 'PRD007', 'qty' => 1], // 1 x 300 = 300
                ['code' => 'PRD008', 'qty' => 2], // 2 x 45 = 90
            ]
        ],
        [
            'sale_id' => 'INV20261003',
            'cust_code' => 'CUST003',
            'date' => '2026-10-19',
            'method' => 'Card',
            'status' => 'Paid',
            'notes' => 'Personal hygiene & detergent purchases',
            'payments' => [
                ['pay_id' => 'PAY-S-003', 'amount' => 370.00, 'date' => '2026-10-19', 'method' => 'Card', 'notes' => 'POS Card terminal auth #44091']
            ],
            'items' => [
                ['code' => 'PRD019', 'qty' => 4], // 4 x 35 = 140
                ['code' => 'PRD025', 'qty' => 2], // 2 x 115 = 230
            ]
        ],
        [
            'sale_id' => 'INV20261004',
            'cust_code' => 'CUST004',
            'date' => '2026-10-19',
            'method' => 'Credit',
            'status' => 'Partial',
            'notes' => 'Corporate office supply order - partial credit account',
            'payments' => [
                ['pay_id' => 'PAY-S-004A', 'amount' => 1540.00, 'date' => '2026-10-19', 'method' => 'Cash', 'notes' => 'Initial token payment'],
                ['pay_id' => 'PAY-S-004B', 'amount' => 500.00,  'date' => '2026-10-20', 'method' => 'UPI',  'notes' => 'Subsequent partial payment via UPI']
            ],
            'items' => [
                ['code' => 'PRD031', 'qty' => 5], // 5 x 40 = 200
                ['code' => 'PRD032', 'qty' => 10], // 10 x 10 = 100
                ['code' => 'PRD034', 'qty' => 8],  // 8 x 280 = 2240 (Retailer had 12, now has 4 left -> triggers LOW STOCK alert!)
            ]
        ],
        [
            'sale_id' => 'INV20261005',
            'cust_code' => null, // Walk-in customer
            'date' => '2026-10-20',
            'method' => 'Cash',
            'status' => 'Paid',
            'notes' => 'Walk-in cash counter sale',
            'payments' => [
                ['pay_id' => 'PAY-S-005', 'amount' => 156.00, 'date' => '2026-10-20', 'method' => 'Cash', 'notes' => 'Exact change received']
            ],
            'items' => [
                ['code' => 'PRD002', 'qty' => 2], // 2 x 40 = 80
                ['code' => 'PRD041', 'qty' => 2], // 2 x 38 = 76
            ]
        ]
    ];

    foreach ($sales_to_seed as $s) {
        $chk_s = mysqli_prepare($conn, "SELECT id FROM sales WHERE sale_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk_s, "s", $s['sale_id']);
        mysqli_stmt_execute($chk_s);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_s))) {
            echo "      [EXISTS] Sale invoice {$s['sale_id']} already recorded.\n";
            continue;
        }

        $c_db_id = !empty($s['cust_code']) ? ($customer_id_map[$s['cust_code']] ?? null) : null;
        $total_amount = 0;
        foreach ($s['items'] as $it) {
            $price = $product_id_map[$it['code']]['sell'];
            $total_amount += ($price * $it['qty']);
        }

        $ins_sale = mysqli_prepare($conn, "INSERT INTO sales (sale_id, customer_id, user_id, sale_date, total_amount, payment_method, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_sale, "siisdsss", $s['sale_id'], $c_db_id, $retailer_id, $s['date'], $total_amount, $s['method'], $s['status'], $s['notes']);
        mysqli_stmt_execute($ins_sale);
        $sale_db_id = mysqli_insert_id($conn);

        // Deduct retailer inventory atomically and record sale items
        $ins_item = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        foreach ($s['items'] as $it) {
            $p_db_id = $product_id_map[$it['code']]['id'];
            $sell_price = $product_id_map[$it['code']]['sell'];
            $subtotal = $sell_price * $it['qty'];
            mysqli_stmt_bind_param($ins_item, "iiidd", $sale_db_id, $p_db_id, $it['qty'], $sell_price, $subtotal);
            mysqli_stmt_execute($ins_item);

            // Deduct stock from retailer
            $update_stock($retailer_id, $p_db_id, -$it['qty'], 'Sale', $s['sale_id'], "Retail POS counter billing");
        }

        // Record payments
        $ins_p = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) VALUES (?, 'Sale', ?, ?, ?, ?, ?, ?)");
        foreach ($s['payments'] as $p) {
            mysqli_stmt_bind_param($ins_p, "ssidsss", $p['pay_id'], $s['sale_id'], $retailer_id, $p['amount'], $p['date'], $p['method'], $p['notes']);
            mysqli_stmt_execute($ins_p);
        }

        echo "      [RECORDED] Sale Invoice {$s['sale_id']}: ₹" . number_format($total_amount, 2) . " (" . ($s['cust_code'] ?? 'Walk-in') . ")\n";
    }
    echo "\n";

    // Commit all changes
    mysqli_commit($conn);
    echo "========================================================\n";
    echo "SAMPLE DATA SEEDING COMPLETED SUCCESSFULLY!\n";
    echo "All 7 Categories, 7 Suppliers, 42 Products, 5 Customers,\n";
    echo "Purchases, Transfers, POS Sales, and Payments are committed.\n";
    echo "========================================================\n";

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "\n[ERROR] Seeding rolled back due to error: " . $e->getMessage() . "\n";
    exit(1);
}
