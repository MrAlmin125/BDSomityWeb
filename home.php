<?php
// ============================================
// TEST FILE - test_db.php
// Upload this file to your server and access it
// ============================================

// Your database credentials
$db_host = 'sql110.infinityfree.com';
$db_user = 'if0_40706792';
$db_pass = 'NewTree1';
$db_name = 'if0_40706792_shopbd';

echo "<h1>Database Connection Test</h1>";

// Test 1: Check connection
echo "<h3>1. Testing Database Connection...</h3>";
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        echo "❌ Connection failed: " . $conn->connect_error . "<br>";
    } else {
        echo "✅ Connected successfully to database: " . $db_name . "<br>";
        
        // Test 2: Check if settings table exists
        echo "<h3>2. Checking Settings Table...</h3>";
        $result = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($result && $result->num_rows > 0) {
            echo "✅ Settings table exists<br>";
            
            // Test 3: Get settings data
            echo "<h3>3. Reading Settings Data...</h3>";
            $result = $conn->query("SELECT key_name, key_value FROM settings");
            if ($result && $result->num_rows > 0) {
                echo "✅ Found " . $result->num_rows . " settings:<br><br>";
                echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
                echo "<tr style='background:#f0f0f0;'><th>Key Name</th><th>Key Value</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($row['key_name']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($row['key_value']) . "</td>";
                    echo "</tr>";
                    
                    // Store specific values for display
                    if ($row['key_name'] == 'somity_name') {
                        $somity_name = $row['key_value'];
                    }
                    if ($row['key_name'] == 'logo_url') {
                        $logo_url = $row['key_value'];
                    }
                }
                echo "</table>";
                
                // Test 4: Display specific values
                echo "<h3>4. Specific Values:</h3>";
                echo "🏷️ Somity Name: <strong>" . ($somity_name ?? 'Not found') . "</strong><br>";
                echo "🖼️ Logo URL: <strong>" . ($logo_url ?? 'Not found') . "</strong><br>";
                
                // Test 5: Check if logo file exists
                if (!empty($logo_url)) {
                    echo "<h3>5. Checking Logo File...</h3>";
                    $full_path = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
                    echo "Looking for: " . $full_path . "<br>";
                    if (file_exists($full_path)) {
                        echo "✅ Logo file exists<br>";
                        echo "<img src='" . htmlspecialchars($logo_url) . "' style='max-width:200px;border:1px solid #ccc;padding:10px;'><br>";
                    } else {
                        echo "❌ Logo file NOT found at: " . $full_path . "<br>";
                        echo "Try checking: /uploads/ folder exists<br>";
                    }
                }
                
            } else {
                echo "❌ No settings found in the settings table<br>";
                echo "Please add some settings like: somity_name, logo_url<br>";
            }
        } else {
            echo "❌ Settings table does not exist<br>";
            echo "Please create the settings table with this SQL:<br>";
            echo "<pre>
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    key_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
            </pre>";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 6: Server info
echo "<h3>6. Server Information:</h3>";
echo "📁 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "📂 Current Directory: " . __DIR__ . "<br>";
echo "🔗 Script URL: " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Test 7: Check uploads folder
echo "<h3>7. Checking Uploads Folder:</h3>";
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
if (file_exists($upload_dir)) {
    echo "✅ Uploads folder exists at: " . $upload_dir . "<br>";
    echo "Contents:<br>";
    $files = scandir($upload_dir);
    if ($files) {
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "<li>" . $file . " - " . filesize($upload_dir . '/' . $file) . " bytes</li>";
            }
        }
        echo "</ul>";
    }
} else {
    echo "❌ Uploads folder does NOT exist at: " . $upload_dir . "<br>";
}

echo "<hr>";
echo "<h3>✅ Test Complete</h3>";
echo "<p>If you see all checks passed, your database is working correctly.</p>";
echo "<p>If you see any errors, they will help identify the problem.</p>";
?>