<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Administration') ?> — Client Portal</title>
    <link rel="stylesheet" href="/css/admin.css">
    <?php if (($activePage ?? '') === 'reference-data'): ?><link rel="stylesheet" href="/css/admin-reference-data.css"><?php endif; ?>
</head>
<body>
<header class="admin-header">
    <a class="brand" href="/admin">Client Portal <span>Admin</span></a>
    <nav>
        <a class="<?= ($activePage ?? '') === 'clients' ? 'active' : '' ?>" href="/admin">Clients</a>
        <a class="<?= ($activePage ?? '') === 'reference-data' ? 'active' : '' ?>" href="/admin/reference-data">Reference Data</a>
        <a href="/logout">Sign out</a>
    </nav>
</header>
<main class="admin-main"><?= $content ?? '' ?></main>
</body>
</html>
