# SimpleMarket (PHP + MySQL, MVC)

A local multi-vendor marketplace for a 4-role delivery business: **admin, seller,
customer, rider**. Home kitchens, university merch stalls and small boutiques sell
through it, and an in-house rider network delivers the orders.
Written in plain PHP with procedural `mysqli` and prepared statements. No frameworks,
no Composer, no build step. Copy it into XAMPP and it runs.

---

## 1. Install (XAMPP)

1. Copy the `Simple-Market` folder into `C:\xampp\htdocs\`
   so it becomes `htdocs/Simple-Market/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/Simple-Market/setup.php` once — it creates the database,
   all 15 tables, the product categories and the default admin. Safe to re-run.
4. Open `http://localhost/Simple-Market/`.
5. Sign in as the default admin: **admin@simplemarket.local / admin123**

The database schema lives in `setup.php` as PHP, so one
page load does the whole install. The admin is seeded only when no admin exists,
so re-running never resets a password you changed. Everyone else signs up on the
register page.

If your MySQL uses a password, change `$db_pass` in `config/database.php`.

---

## 2. Folder structure

```
SimpleMarket/
├── index.php                  Front controller: the ONLY entry point (router)
├── setup.php                  Schema + categories + default admin (re-runnable)
├── README.md
│
├── config/
│   ├── config.php             App constants: fees, commission split, base URL
│   └── database.php           The single $conn (mysqli procedural)
│
├── helpers/
│   ├── auth_helper.php        Sessions, login/logout, remember-me, require_role()
│   ├── csrf_helper.php        Token mint/verify + the POST guard
│   ├── validation_helper.php  cleanInput(), validate_*(), money()
│   ├── view_helper.php        url(), render(), e(), json_response()
│   └── functions_helper.php   Flash messages
│
├── models/                    M — every SQL query lives here
│   ├── user_model.php         All 4 roles + the seller/rider profile tables
│   ├── product_model.php      Seller catalogue + the customer-facing browse
│   ├── order_model.php        Checkout, status rules, delivery claim/advance
│   ├── offer_model.php        Price bidding: place, counter, settle, redeem
│   ├── review_model.php       Product reviews + shop ratings (two tables)
│   ├── message_model.php      Order chat + who is allowed to read it
│   ├── earning_model.php      Rider earnings and rollups
│   ├── note_model.php         The rider's private delivery notes
│   ├── notification_model.php
│   └── report_model.php       Admin sales/commission rollups
│
├── controllers/               C — request handling, validation, decisions
│   ├── auth_controller.php    home / login / register / logout
│   ├── account_controller.php Password, notifications, chat — shared by all roles
│   ├── admin_controller.php
│   ├── seller_controller.php
│   ├── customer_controller.php
│   ├── rider_controller.php
│   └── ajax_controller.php    all JSON endpoints
│
├── views/                     V — HTML only
│   ├── partials/              header, footer, navbar, chat, not_found
│   ├── auth/                  home.php, login.php, register.php
│   ├── account/               change_password.php, notifications.php
│   ├── admin/                 dashboard, users, approvals, commission, sales, search
│   ├── seller/                dashboard, products, low_stock, orders, bidding, payments
│   ├── customer/              dashboard, search, checkout, orders, tracking, offers
│   └── rider/                 dashboard, deliveries, notes, earnings, search
│
├── assets/
│   ├── css/style.css          + one accent file per role
│   └── js/                    validation.js, main.js (badge poll), chat_poll.js
│
├── uploads/products/          Product images
├── docs/diagrams/             ER + use case diagrams (Mermaid source)
└── tests/                     Static checkers + runtime suites
```

**The MVC rule used throughout:** a view never runs a query, and a model never
prints HTML. The controller sits in the middle: it reads `$_POST`, validates,
calls the model, then hands data to the view through `render()`.

---

## 3. How the router works

Every URL looks like this:

```
index.php?page=<where>&action=<what to do>&id=<row id>
```

| URL | What happens |
| --- | --- |
| `index.php?page=home` | Public landing page |
| `index.php?page=login` | Login page |
| `index.php?page=register` | Signup page |
| `index.php?page=seller&action=products` | Seller's product desk (list mode) |
| `index.php?page=seller&action=products&edit=4` | Load product 4 into the form |
| `index.php?page=customer&action=checkout&product_id=3&offer_id=7` | Buy product 3 at an accepted bid price |
| `index.php?page=admin&action=users&q=demo&role=seller` | Filtered account search |
| `index.php?page=ajax&action=poll_messages&order_id=12` | Returns JSON |
| `index.php?page=logout` | Sign out |

`index.php` loads config → helpers → models → controllers, starts the session and
runs the CSRF guard, then sends the request to one controller. `require_role('admin')`
blocks anyone who is not an admin before the controller even starts.

Links are never hard-coded — every one is built with `url('seller', 'products')`,
so the routing scheme lives in a single function.

---

## 4. The four roles

Each role does full **Create, Read, Update, Delete and Search** on its own dashboard,
plus three features that appear nowhere else.

| Role | Manages (CRUD) | Feature 1 | Feature 2 | Feature 3 |
| --- | --- | --- | --- | --- |
| **Admin** | User accounts (all roles) | Commission calculator — set a per-seller rate, with a live estimator | Seller approval queue, sortable by application date | Sales overview: per-shop gross, commission, payout, date-filtered |
| **Seller** | Products | Low stock alert list | Price bidding — accept, reject or counter a customer's offer | Choose which payment methods the shop accepts |
| **Customer** | Orders and bids | Fast delivery option at checkout | Product feedback after the order arrives | Separate shop rating for the service |
| **Rider** | Private delivery notes | Vehicle details and availability status | Order chat with the customer and shop (AJAX polling) | Earnings calculator: totals, averages, best day |

No feature appears on two dashboards.

### How the roles connect

- A **customer** bids below the listed price → the **seller** counters → the customer
  accepts → checkout carries the agreed price and stamps the bid as spent, so one
  bid can never be redeemed twice.
- The seller confirms the order → **preparing** → it appears in the open pool.
- Any **rider** claims it — a single guarded `UPDATE`, so two riders cannot take the
  same order — then marks it out for delivery, then delivered.
- Delivering writes the rider's earnings row (80% of the delivery fee) and frees them
  for the next job.
- Only once an order is **delivered** can the customer review the product and rate
  the shop.
- A new **seller** starts as `pending` and cannot log in until an **admin** approves
  the shop.

---

## 5. Requirement checklist

| Requirement | Where to look |
| --- | --- |
| **MVC** | `models/`, `controllers/`, `views/`, routed by `index.php` |
| **DB (MySQLi procedural)** | every function in `models/` uses `mysqli_prepare` |
| **Auth (session + cookie)** | `helpers/auth_helper.php`, `controllers/auth_controller.php` |
| **PHP validation** | the per-field error block at the top of every controller action |
| **JS validation** | `assets/js/validation.js`, driven by `data-*` attributes on the inputs |
| **AJAX / JSON** | `controllers/ajax_controller.php` + `chat_poll.js` / `main.js` |
| **UI (HTML/CSS)** | `views/`, `assets/css/style.css` |
| **Basic web security** | see section 6 |
| **Feature completeness** | CRUD + search + 3 features per role |

---

## 6. Security, and why each piece is there

| Attack | Defence | File |
| --- | --- | --- |
| SQL injection | Prepared statements everywhere — user text is never glued into SQL | all `models/` |
| SQL injection via sort/filter | A column name cannot be bound, so the SQL *shape* comes from a whitelist and the values are still bound | `report_model.php`, `user_model.php` |
| Stolen passwords | `password_hash()` on save, `password_verify()` on login | `user_model.php` |
| XSS | `e()` wraps every value printed into HTML | `view_helper.php`, all views |
| CSRF | A token in every POST form, checked in one place that runs on include | `csrf_helper.php` |
| Session fixation | `session_regenerate_id(true)` right after a successful login | `auth_helper.php` |
| Cookie theft | `httponly` + `samesite=Lax` on the session cookie | `auth_helper.php` |
| Stolen "remember me" cookie | Only a SHA-256 **hash** of the token is stored, so a dumped database cannot be replayed as a login | `auth_helper.php` |
| Idle machines | Automatic sign-out after 30 minutes | `SESSION_IDLE_SECONDS` |
| Wrong role | `require_role()` before the controller; each AJAX action re-checks the session | `index.php`, `ajax_controller.php` |
| URL tampering | Ownership is in the `WHERE` clause of the write, not just a lookup before it (`… AND seller_id = ?`) | all `models/` |
| Email guessing | Wrong email and wrong password give the same message | `auth_controller.php` |
| Self-lockout | An admin cannot delete the account they are signed in with | `admin_controller.php` |
| Overselling under load | Stock is checked *inside* the write (`AND stock_quantity >= ?`), not before it | `order_model.php` |
| Double-claiming a delivery | One guarded `UPDATE`; the second rider changes zero rows | `order_model.php` |
| Suspended user returning | Suspending an account deletes its remember-me tokens | `user_model.php` |

Two things worth saying out loud:

1. **JavaScript validation is a convenience, not a defence.** Anyone can turn
   JavaScript off. That is why every controller repeats the checks in PHP.
2. **Deleting is refused, not cascaded, where history matters.** Almost every foreign
   key into `users` and `products` cascades, so removing a traded account or an
   ordered product would silently take real orders with it. Those two paths refuse
   and point at suspend / deactivate instead.

---

## 7. Settings you can change

All in `config/config.php`:

```php
define('SITE_NAME',            'SimpleMarket');
define('BASE_URL',             '/SimpleMarket/'); // change if the folder is renamed
define('STANDARD_DELIVERY_FEE', 30.00);  // flat fee, standard speed
define('FAST_DELIVERY_FEE',     70.00);  // flat fee, fast delivery
define('RIDER_EARNING_RATE',    0.80);   // share of the fee the rider keeps
```

And in `helpers/auth_helper.php`:

```php
define('REMEMBER_DAYS',        30);   // how long a "remember me" cookie lasts
define('SESSION_IDLE_SECONDS', 1800); // idle sign-out, in seconds
```

The per-seller commission rate is **not** a constant — an admin sets it per shop from
the commission calculator, and each order stores the amount it was charged, so a later
rate change never rewrites past orders.

---

## 8. Test accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@simplemarket.local` | `admin123` |
| Seller | sign up on the register page | |
| Customer | sign up on the register page | |
| Rider | sign up on the register page | |

Nobody can sign up as an admin — the register page only accepts the other three
roles, and the controller checks that list again on the server. New admins are
created by an existing admin.

A seller cannot log in until an admin approves the shop, under **Approvals**.

> Change the default admin password from **Profile → Change Password** before running
> this anywhere other than a local machine.

---

Copyright (c) 2026 Safayet Gazi Sopan. All rights reserved.