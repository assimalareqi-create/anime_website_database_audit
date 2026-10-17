<?php
/**
 * صفحة البحث المتقدم عن الأنمي
 */
require_once 'includes/init.php';

// معلمات البحث
$search = sanitizeInput($_GET['search'] ?? '');
$genreId = isset($_GET['genre_id']) && is_numeric($_GET['genre_id']) ? (int)$_GET['genre_id'] : null;
$status = sanitizeInput($_GET['status'] ?? '');
$year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
$sort = sanitizeInput($_GET['sort'] ?? 'title');
$order = sanitizeInput($_GET['order'] ?? 'ASC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['title', 'release_year', 'created_at', 'views', 'rating'];
$allowedOrderValues = ['ASC', 'DESC'];
$allowedStatusValues = ['مستمر', 'مكتمل', 'متوقف', ''];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'title';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'ASC';
}

if (!in_array($status, $allowedStatusValues)) {
    $status = '';
}

// الحصول على قائمة السنوات للتصفية
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT release_year 
        FROM anime 
        WHERE release_year > 0 
        ORDER BY release_year DESC
    ");
    $stmt->execute();
    $yearsList = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة السنوات: " . $e->getMessage());
    $yearsList = [];
}

// الحصول على قائمة التصنيفات للتصفية
try {
    $stmt = $conn->prepare("
        SELECT g.*, COUNT(ag.anime_id) as anime_count
        FROM genres g
        LEFT JOIN anime_genres ag ON g.id = ag.genre_id
        GROUP BY g.id
        ORDER BY g.name
    ");
    $stmt->execute();
    $genresList = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة التصنيفات: " . $e->getMessage());
    $genresList = [];
}

// بناء استعلام البحث
$sql = "
    SELECT a.*, 
    COUNT(DISTINCT e.id) as episodes_count, 
    SUM(e.views) as total_views,
    AVG(r.rating) as avg_rating, 
    COUNT(DISTINCT r.id) as ratings_count
    FROM anime a
    LEFT JOIN episodes e ON a.id = e.anime_id
    LEFT JOIN ratings r ON a.id = r.anime_id
";

// إضافة شرط التصنيف إذا تم تحديده
if ($genreId !== null) {
    $sql .= " JOIN anime_genres ag ON a.id = ag.anime_id AND ag.genre_id = :genre_id";
}

$sql .= " WHERE 1=1";
$params = [];

// إضافة شروط البحث
if (!empty($search)) {
    $sql .= " AND (a.title LIKE :search OR a.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $status;
}

if ($year !== null) {
    $sql .= " AND a.release_year = :year";
    $params[':year'] = $year;
}

if ($genreId !== null) {
    $params[':genre_id'] = $genreId;
}

$sql .= " GROUP BY a.id";

// إضافة الترتيب
if ($sort === 'rating') {
    $sql .= " HAVING COUNT(DISTINCT r.id) > 0";
    $sql .= " ORDER BY avg_rating $order, ratings_count DESC";
} elseif ($sort === 'views') {
    $sql .= " ORDER BY total_views $order";
} else {
    $sql .= " ORDER BY a.$sort $order";
}

// التقسيم إلى صفحات
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

$countSql = str_replace("SELECT a.*, \nCOUNT(DISTINCT e.id) as episodes_count, \nSUM(e.views) as total_views,\nAVG(r.rating) as avg_rating, \nCOUNT(DISTINCT r.id) as ratings_count", "SELECT COUNT(DISTINCT a.id) as total", $sql);
$countSql = preg_replace("/GROUP BY a.id.*/", "", $countSql);

try {
    // الحصول على إجمالي عدد النتائج
    $countStmt = $conn->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
   $countRow = $countStmt->fetch();
$totalAnime = isset($countRow['total']) ? (int)$countRow['total'] : 0;
    $totalPages = ceil($totalAnime / $perPage);
    
    // الحصول على نتائج البحث
    $sql .= " LIMIT :offset, :perPage";
    $params[':offset'] = $offset;
    $params[':perPage'] = $perPage;
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':perPage') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $animeList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في البحث عن الأنمي: " . $e->getMessage());
    $animeList = [];
    $totalAnime = 0;
    $totalPages = 1;
}

// تنسيق التقييم إلى نجوم
function formatRating($rating) {
    $rating = round($rating * 2) / 2; // تقريب إلى أقرب 0.5
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    
    // النجوم الكاملة
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star text-warning"></i>';
    }
    
    // نصف نجمة إذا وجدت
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    // النجوم الفارغة
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star text-warning"></i>';
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بحث الأنمي - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <!-- قسم البحث -->
    <section class="section-padding">
        <div class="container">
            <div class="row">
                <!-- فلاتر البحث -->
                <div class="col-lg-3">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">فلاتر البحث</h2>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" id="searchForm">
                                <div class="mb-3">
                                    <label for="search" class="form-label">كلمة البحث</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث عن أنمي...">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="genre_id" class="form-label">التصنيف</label>
                                    <select class="form-select" id="genre_id" name="genre_id">
                                        <option value="">الكل</option>
                                        <?php foreach ($genresList as $genre): ?>
                                            <option value="<?php echo $genre['id']; ?>" <?php echo $genreId === $genre['id'] ? 'selected' : ''; ?>>
                                                <?php echo $genre['name']; ?> (<?php echo $genre['anime_count']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">الكل</option>
                                        <option value="مستمر" <?php echo $status === 'مستمر' ? 'selected' : ''; ?>>مستمر</option>
                                        <option value="مكتمل" <?php echo $status === 'مكتمل' ? 'selected' : ''; ?>>مكتمل</option>
                                        <option value="متوقف" <?php echo $status === 'متوقف' ? 'selected' : ''; ?>>متوقف</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="year" class="form-label">سنة الإصدار</label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="">الكل</option>
                                        <?php foreach ($yearsList as $yearOption): ?>
                                            <option value="<?php echo $yearOption; ?>" <?php echo $year === $yearOption ? 'selected' : ''; ?>>
                                                <?php echo $yearOption; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sort" class="form-label">ترتيب حسب</label>
                                    <select class="form-select" id="sort" name="sort">
                                        <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>الاسم</option>
                                        <option value="release_year" <?php echo $sort === 'release_year' ? 'selected' : ''; ?>>سنة الإصدار</option>
                                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>تاريخ الإضافة</option>
                                        <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>المشاهدات</option>
                                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>التقييم</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="order" class="form-label">الترتيب</label>
                                    <select class="form-select" id="order" name="order">
                                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>تصاعدي</option>
                                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>تنازلي</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> بحث
                                    </button>
                                    <a href="anime_list.php" class="btn btn-secondary">
                                        <i class="fas fa-redo me-1"></i> إعادة تعيين
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- التصنيفات الشائعة -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">التصنيفات الشائعة</h2>
                        </div>
                        <div class="card-body">
                            <div class="genres-container">
                                <?php foreach (array_slice($genresList, 0, 15) as $genre): ?>
                                    <a href="anime_list.php?genre_id=<?php echo $genre['id']; ?>" class="genre-badge">
                                        <?php echo $genre['name']; ?>
                                        <span class="genre-count"><?php echo $genre['anime_count']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                                
                                <?php if (count($genresList) > 15): ?>
                                    <a href="genres.php" class="genre-badge more-genres">
                                        المزيد <i class="fas fa-arrow-left"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- نتائج البحث -->
                <div class="col-lg-9">
                    <div class="search-results-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h3">نتائج البحث</h1>
                            <span class="search-count">
                                <?php echo $totalAnime; ?> نتيجة
                            </span>
                        </div>
                        
                        <?php if (!empty($search) || $genreId !== null || !empty($status) || $year !== null): ?>
                            <div class="search-filters mt-2">
                                <div class="d-flex flex-wrap">
                                    <?php if (!empty($search)): ?>
                                        <div class="search-filter-tag">
                                            <span>البحث: <?php echo $search; ?></span>
                                            <a href="<?php echo removeQueryParam('search'); ?>" class="filter-remove"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($genreId !== null): ?>
                                        <?php 
                                        $genreName = '';
                                        foreach ($genresList as $genre) {
                                            if ($genre['id'] === $genreId) {
                                                $genreName = $genre['name'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <div class="search-filter-tag">
                                            <span>التصنيف: <?php echo $genreName; ?></span>
                                            <a href="<?php echo removeQueryParam('genre_id'); ?>" class="filter-remove"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($status)): ?>
                                        <div class="search-filter-tag">
                                            <span>الحالة: <?php echo $status; ?></span>
                                            <a href="<?php echo removeQueryParam('status'); ?>" class="filter-remove"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($year !== null): ?>
                                        <div class="search-filter-tag">
                                            <span>السنة: <?php echo $year; ?></span>
                                            <a href="<?php echo removeQueryParam('year'); ?>" class="filter-remove"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="search-filter-tag clear-all">
                                        <a href="anime_list.php">مسح الكل</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($animeList)): ?>
                        <div class="row">
                            <?php foreach ($animeList as $anime): ?>
                                <div class="col-6 col-md-4 col-xl-3 mb-4">
                                    <div class="anime-card">
                                        <div class="anime-thumbnail">
                                            <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                                <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid">
                                                <div class="anime-overlay">
                                                    <span class="anime-status"><?php echo $anime['status']; ?></span>
                                                    <span class="anime-episodes"><?php echo $anime['episodes_count']; ?> حلقة</span>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="anime-info">
                                            <h3 class="anime-title">
                                                <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                                    <?php echo $anime['title']; ?>
                                                </a>
                                            </h3>
                                            <div class="anime-meta">
                                                <span class="anime-year"><?php echo $anime['release_year']; ?></span>
                                                <?php if (!empty($anime['avg_rating'])): ?>
                                                    <span class="anime-rating">
                                                        <i class="fas fa-star text-warning"></i>
                                                        <?php echo number_format($anime['avg_rating'], 1); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- التقسيم إلى صفحات -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo buildPaginationUrl($page - 1); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl(1) . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildPaginationUrl($i); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor;
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl($totalPages) . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo buildPaginationUrl($page + 1); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لم يتم العثور على نتائج مطابقة. يرجى تعديل معايير البحث والمحاولة مرة أخرى.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
</body>
</html>

<?php
// دالة لإزالة معلمة من رابط الاستعلام
function removeQueryParam($param) {
    $params = $_GET;
    unset($params[$param]);
    
    if (empty($params)) {
        return 'anime_list.php';
    }
    
    return 'anime_list.php?' . http_build_query($params);
}

// دالة لبناء رابط التقسيم إلى صفحات
function buildPaginationUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    
    return 'anime_list.php?' . http_build_query($params);
}
?>
