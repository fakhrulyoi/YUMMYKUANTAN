RewriteEngine On

# Handle CORS preflight requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# API Routes
RewriteRule ^api/products/?$ api/products.php [L]
RewriteRule ^api/products/([0-9]+)/?$ api/products.php?action=single&id=$1 [L]
RewriteRule ^api/products/search/(.+)/?$ api/products.php?action=search&q=$1 [L]

RewriteRule ^api/orders/?$ api/orders.php [L]
RewriteRule ^api/orders/customer/([0-9]+)/?$ api/orders.php?customer_id=$1 [L]

RewriteRule ^api/customers/?$ api/customers.php [L]
RewriteRule ^api/customers/(register|login)/?$ api/customers.php?action=$1 [L]

# Enable CORS
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"