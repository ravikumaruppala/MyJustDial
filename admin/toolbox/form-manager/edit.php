<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require __DIR__ . '/../../../includes/form_fields.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM forms WHERE form_id=?"); $stmt->execute([$id]); $form = $stmt->fetch();
if (!$form) { flash('warning','Custom form not found or this is a protected default form.'); redirect('/admin/toolbox/form-manager/index.php'); }
if (tableExists($pdo,'users_meta')) { $nickStmt=$pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_nickname' LIMIT 1"); $nickStmt->execute([$id]); $savedNickname=$nickStmt->fetchColumn(); $form['form_nickname']=$savedNickname!==false?$savedNickname:($form['form_title']?:$form['form_name']); } else { $form['form_nickname']=$form['form_title']?:$form['form_name']; }
$fields = loadBuilderFields($pdo, $form['form_name']);
$isNewForm = count($fields) === 0;
$fieldData = loadBuilderLists($pdo);
$pageTitle = 'Edit Form'; $activeMenu = 'forms';
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"><link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/form-builder.css">';
require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/editor.php';
require __DIR__ . '/../../../includes/footer.php';
