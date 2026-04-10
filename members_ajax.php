<?php
require_once 'config.php';

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$interest_filter = isset($_GET['interest']) ? sanitizeInput($_GET['interest']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 7;
$offset = ($page - 1) * $rows_per_page;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(m.fullname LIKE ? OR m.email LIKE ? OR m.member_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "health_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($interest_filter)) {
    $where_clauses[] = "m.membership_type = ?";
    $params[] = $interest_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM (
    SELECT 
        m.*,
        CASE 
            WHEN m.status = 'expired' OR m.end_date < CURDATE() THEN 'Expired'
            WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
            ELSE 'Healthy'
        END as health_status
    FROM members m
) AS sub $where_sql";

$count_result = executeQuery($count_sql, $params, $types);
$total_rows = $count_result->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Get members with pagination
$sql = "SELECT 
            m.*,
            CASE 
                WHEN m.status = 'expired' OR m.end_date < CURDATE() THEN 'Expired'
                WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
                ELSE 'Healthy'
            END as health_status,
            DATEDIFF(m.end_date, CURDATE()) as days_remaining
        FROM members m
        $where_sql
        ORDER BY 
            CASE 
                WHEN m.end_date < CURDATE() THEN 3
                WHEN m.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                ELSE 1
            END,
            m.end_date ASC
        LIMIT ? OFFSET ?";

$params[] = $rows_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = executeQuery($sql, $params, $types);
$members = $stmt->get_result();

// Build HTML for table rows
$html = '';
if ($members && $members->num_rows > 0) {
    while($member = $members->fetch_assoc()) {
        $health_class = '';
        if ($member['health_status'] == 'Healthy') {
            $health_class = 'healthy';
        } elseif ($member['health_status'] == 'Expiring Soon') {
            $health_class = 'expiring';
        } else {
            $health_class = 'expired';
        }
        
        $days_left = $member['days_remaining'];
        $days_display = $days_left < 0 ? 'Expired' : $days_left . ' days';
        
        $html .= '<tr class="clickable-row" data-id="' . $member['id'] . '">';
        $html .= '<td><strong>' . htmlspecialchars($member['member_code']) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($member['fullname']) . '</td>';
        $html .= '<td>' . htmlspecialchars($member['email']) . '</td>';
        $html .= '<td><span class="badge membership">' . htmlspecialchars($member['membership_type']) . '</span></td>';
        $html .= '<td><span class="badge ' . $health_class . '">' . $member['health_status'] . '</span></td>';
        $html .= '<td>' . date('Y-m-d', strtotime($member['start_date'])) . '</td>';
        $html .= '<td>' . date('Y-m-d', strtotime($member['end_date'])) . '</td>';
        $html .= '<td>' . $days_display . '</td>';
        $html .= '</tr>';
    }
} else {
    $html = '<tr><td colspan="8" style="text-align: center; padding: 40px;">
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No members found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
             </td></tr>';
}

// Build pagination HTML
$pagination_html = '';
if ($total_pages > 1) {
    $start_num = (($page - 1) * $rows_per_page) + 1;
    $end_num = min($page * $rows_per_page, $total_rows);
    
    $pagination_html = '<div class="pagination-info">Showing ' . $start_num . ' to ' . $end_num . ' of ' . $total_rows . ' members</div>';
    $pagination_html .= '<div class="pagination-controls">';
    
    if ($page > 1) {
        $pagination_html .= '<a href="#" class="btn btn-secondary page-link" data-page="' . ($page - 1) . '"><i class="fas fa-chevron-left"></i> Previous</a>';
    }
    
    // Show limited page numbers
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $page + 3);
    
    if ($start_page > 1) {
        $pagination_html .= '<a href="#" class="page-number page-link" data-page="1">1</a>';
        if ($start_page > 2) $pagination_html .= '<span class="page-number" style="border: none;">...</span>';
    }
    
    for($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $pagination_html .= '<button class="page-number active">' . $i . '</button>';
        } else {
            $pagination_html .= '<a href="#" class="page-number page-link" data-page="' . $i . '">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) $pagination_html .= '<span class="page-number" style="border: none;">...</span>';
        $pagination_html .= '<a href="#" class="page-number page-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
    }
    
    if ($page < $total_pages) {
        $pagination_html .= '<a href="#" class="btn btn-secondary page-link" data-page="' . ($page + 1) . '">Next <i class="fas fa-chevron-right"></i></a>';
    }
    
    $pagination_html .= '</div>';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => $html,
    'pagination' => $pagination_html
]);
?>