<?php

ini_set('display_errors', 1);

$config = [
    'title' => 'Asterisk CDR browser',
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=asterisk',
        'username' => 'asterisk',
        'passwd' => 'asterisk',
        'table' => 'cdr',
    ],
    'columns' => [
        // https://docs.asterisk.org/Configuration/Reporting/Call-Detail-Records-CDR/CDR-Variables/
        ['id' => 'calldate', 'name' => 'Call date'],
        //['id' => 'accountcode', 'name' => 'Account'],
        ['id' => 'src', 'name' => 'Source'],
        ['id' => 'dst', 'name' => 'Destination'],
        ['id' => 'dcontext', 'name' => 'Destination context'],
        ['id' => 'clid', 'name' => 'Caller ID'],
        ['id' => 'channel', 'name' => 'Channel name'],
        ['id' => 'dstchannel', 'name' => 'Destination channel'],
        ['id' => 'lastapp', 'name' => 'Last app executed'],
        ['id' => 'lastdata', 'name' => 'Last app args'],
        ['id' => 'start', 'name' => 'Time started'],
        ['id' => 'answer', 'name' => 'Time answered'],
        ['id' => 'end', 'name' => 'Time ended'],
        ['id' => 'duration', 'name' => 'Duration'],
        ['id' => 'billsec', 'name' => 'Bill seconds'],
        ['id' => 'disposition', 'name' => 'Disposition'], // ANSWERED, NO ANSWER, BUSY
        //['id' => 'amaflags', 'name' => 'Flags'], // DOCUMENTATION, BILL, IGNORE etc
        //['id' => 'uniqueid', 'name' => 'Unique ID'],
        //['id' => 'userfield', 'name' => 'User field'],
    ],
	'limit' => 100,
	'searchCols' => ['clid', 'src', 'dst', 'dcontext'],
    'assets' => [
        'styles' => [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css',
			'https://unpkg.com/gridjs/dist/theme/mermaid.min.css',
        ],
        'scripts' => [
			'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js',
			'https://unpkg.com/gridjs/dist/gridjs.umd.js',
        ],
    ],
];

function h($var)
{
    return htmlspecialchars($var);
}

function debug(...$vars)
{
    foreach($vars as $var) {
        echo '<pre>';
        echo htmlspecialchars(print_r($var, true));
        echo '</pre>';
    }
}

function isAjax() 
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'));
}

if (isAjax()) {
    $dbh = new PDO($config['db']['dsn'], $config['db']['username'], $config['db']['passwd']);
    $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

	$limit = intval($_GET['limit'] ?? $config['limit']);
	$offset = intval($_GET['offset'] ?? 0);
    $search = $_GET['search'] ?? '';
	$order = $_GET['order'] ?? 'calldate';
	$dir = $_GET['dir'] ?? 'desc';
    $conditions = [];
	$values = compact('limit', 'offset');

    if (!empty($search)) {
		$conditions[] = implode(' OR ', array_map(function ($col) {
			return "{$col} LIKE :search";
		}, $config['searchCols']));
        $values['search'] = "%{$search}%";
    }

    $conditions = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
	$stmt = $dbh->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$config['db']['table']} {$conditions} ORDER BY {$order} {$dir} LIMIT :offset, :limit");	
	
    foreach ($values as $param => $value) {
        $stmt->bindValue($param, $value, in_array($param, ['limit', 'offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll();
    $total = $dbh->query("SELECT FOUND_ROWS()")->fetchColumn();
	
    echo json_encode(compact('results', 'total'));
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php foreach($config['assets']['styles'] as $style): ?>
        <link href="<?php echo h($style); ?>" rel="stylesheet">
    <?php endforeach; ?>

    <title><?php echo h($config['title']); ?></title>

    <style>
        body {
            padding: 12px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
		<div id="wrapper" data-columns="<?php echo h(json_encode($config['columns'])); ?>"></div>
    </div>

    <?php foreach($config['assets']['scripts'] as $script): ?>
        <script src="<?php echo h($script); ?>"></script>
    <?php endforeach; ?>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			let wrapper = document.getElementById('wrapper');
			let columns = JSON.parse(wrapper.dataset.columns);
			const grid = new gridjs.Grid({
				columns: columns,
				server: {
					url: '<?php echo $_SERVER['REQUEST_URI']; ?>',
					headers: {'X-Requested-With': 'XMLHttpRequest'},
					then: data => data.results.map(row => {
						return columns.map(col => row[col.id]);
					}),
					total: data => data.total
				},
				search: {
					server: {
						url: (prev, keyword) => `${prev}?search=${keyword}`
					},
					debounceTimeout: 1000
				},
				pagination: {
					enabled: true,
					limit: <?php echo $config['limit']; ?>,
					server: {
						url: (prev, page, limit) => `${prev}${prev.includes('?') ? '&' : '?'}limit=${limit}&offset=${page * limit}`
					}
				},
				sort: {
					multiColumn: false,
					server: {
						url: (prev, cols) => {
							if (!cols.length) return prev;

							const col = cols[0];
							const dir = col.direction === 1 ? 'asc' : 'desc';
							let order = columns[col.index]['id'];
							
							return `${prev}${prev.includes('?') ? '&' : '?'}order=${order}&dir=${dir}`;
						}
					}
				}
			}).render(wrapper);
		});
	</script>
</body>
</html>