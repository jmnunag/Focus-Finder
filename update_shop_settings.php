<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if(isset($_POST['path'])) {
    $path = $_POST['path'];
    if(strpos($path, '..') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid path']);
        exit;
    }
    if(unlink($path)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
    }
    exit;
}

$owner_email = $_SESSION['email'];

if(isset($_POST['description'])) {
    try {
        $query = "UPDATE study_locations 
                 SET description = ?,
                     time = ?,
                     min_price = ?,
                     max_price = ?
                 WHERE owner_email = ?";
        
        $stmt = $conn->prepare($query);
        
        $description = strip_tags($_POST['description']);
        $time = strip_tags($_POST['time']);
        $min_price = floatval($_POST['min_price']);
        $max_price = floatval($_POST['max_price']);
        
        $stmt->bind_param("ssdds", 
            $description,
            $time,
            $min_price,
            $max_price,
            $owner_email
        );
        
        if($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Establishment information updated'
            ]);
        } else {
            throw new Exception("Failed to update information");
        }
    } catch(Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

if(isset($_POST['selectedCategories'])) {
    try {
        $tags = $_POST['selectedCategories'];
        $updates = [];
        $params = [];
        $types = "";

        if(isset($_FILES['shopIcon']) && $_FILES['shopIcon']['size'] > 0) {
            $targetDir = "images/icons/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
        
            $iconFile = $_FILES['shopIcon'];
            $iconExt = strtolower(pathinfo($iconFile['name'], PATHINFO_EXTENSION));
            $iconNewName = "icon_" . time() . "_" . $owner_email . "." . $iconExt;
            $iconPath = $targetDir . $iconNewName;
        
            if(move_uploaded_file($iconFile['tmp_name'], $iconPath)) {
                $stmt = $conn->prepare("SELECT image_url FROM study_locations WHERE owner_email = ?");
                $stmt->bind_param("s", $owner_email);
                $stmt->execute();
                $oldIcon = $stmt->get_result()->fetch_assoc()['image_url'];
                if(file_exists($oldIcon) && strpos($oldIcon, 'images/FFv1.png') === false) {
                    unlink($oldIcon);
                }
        
                $updates[] = "image_url = ?";
                $params[] = $iconPath;
                $types .= "s";
            }
        }
        
        if(isset($_FILES['menuImage']) && !empty($_FILES['menuImage']['name'][0])) {
            $shopName = getShopName($conn, $owner_email);
            $folder_name = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $shopName);
            $targetDir = "menus/" . trim($folder_name) . "/";
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            foreach($_FILES['menuImage']['tmp_name'] as $key => $tmp_name) {
                if($_FILES['menuImage']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['menuImage']['name'][$key];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_name = "menu_" . time() . "_" . $key . "." . $file_ext;
                    $target_file = $targetDir . $new_name;

                    move_uploaded_file($tmp_name, $target_file);
                }
            }
        }

        if(isset($_FILES['shopImages'])) {
            $shopName = getShopName($conn, $owner_email);
            $targetDir = "locations/" . $shopName . "/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            foreach($_FILES['shopImages']['tmp_name'] as $key => $tmp_name) {
                if($_FILES['shopImages']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['shopImages']['name'][$key];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_name = "img_" . time() . "_" . $key . "." . $file_ext;
                    $target_file = $targetDir . $new_name;

                    move_uploaded_file($tmp_name, $target_file);
                }
            }
        }

        $updates[] = "tags = ?";
        $params[] = $tags;
        $types .= "s";

        $params[] = $owner_email;
        $types .= "s";

        if(!empty($updates)) {
            $query = "UPDATE study_locations SET " . implode(", ", $updates) . " WHERE owner_email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Shop information updated successfully'
                ]);
            } else {
                throw new Exception("Failed to update shop information");
            }
        }

    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

function getShopName($conn, $email) {
    $stmt = $conn->prepare("SELECT name FROM study_locations WHERE owner_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['name'];
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>