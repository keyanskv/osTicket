<?php
/*********************************************************************
    ajax.dashboard.php

    AJAX interface for custom dashboard features
    - Departments tab
    - Help Topics tab  
    - Agents tab

    Custom extension for osTicket 1.18+

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

if (!defined('INCLUDE_DIR')) die('403');

require_once(INCLUDE_DIR . 'class.ajax.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.dept.php');
require_once(INCLUDE_DIR . 'class.topic.php');
require_once(INCLUDE_DIR . 'class.staff.php');
require_once(INCLUDE_DIR . 'class.export.php');

class DashboardAjaxAPI extends AjaxController {

    /**
     * Get all departments list
     */
    function getDepartments() {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $departments = Dept::getDepartments(array('activeonly' => true));
        
        $result = array();
        foreach ($departments as $id => $name) {
            $result[] = array(
                'id' => $id,
                'name' => $name
            );
        }

        return $this->json_encode(array('departments' => $result));
    }

    /**
     * Get tickets by department ID
     */
    function getTicketsByDepartment($dept_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$dept_id || !is_numeric($dept_id))
            Http::response(400, 'Invalid department ID');

        $dept = Dept::lookup($dept_id);
        if (!$dept)
            Http::response(404, 'Department not found');

        // Build the query
        $sql = "SELECT 
                t.ticket_id,
                t.number AS ticket_number,
                CONCAT(u.name) AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_agent,
                ts.name AS status_name,
                tc.subject,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE t.dept_id = " . db_input($dept_id) . "
            ORDER BY t.created DESC
            LIMIT 100";

        $tickets = array();
        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                $tickets[] = array(
                    'ticket_id' => $row['ticket_id'],
                    'ticket_number' => $row['ticket_number'],
                    'user' => $row['user_name'] ?: __('Guest'),
                    'assigned_agent' => $row['assigned_agent'] ?: __('Unassigned'),
                    'status' => $row['status_name'],
                    'subject' => Format::truncate($row['subject'], 50),
                    'created' => Format::datetime($row['created'])
                );
            }
        }

        return $this->json_encode(array(
            'department' => $dept->getName(),
            'tickets' => $tickets,
            'count' => count($tickets)
        ));
    }

    /**
     * Get all help topics list
     */
    function getHelpTopics() {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $topics = Topic::getHelpTopics(false, false, true);
        
        $result = array();
        foreach ($topics as $id => $name) {
            $result[] = array(
                'id' => $id,
                'name' => $name
            );
        }

        return $this->json_encode(array('topics' => $result));
    }

    /**
     * Get tickets by help topic ID
     */
    function getTicketsByTopic($topic_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$topic_id || !is_numeric($topic_id))
            Http::response(400, 'Invalid topic ID');

        $topic = Topic::lookup($topic_id);
        if (!$topic)
            Http::response(404, 'Help topic not found');

        // Build the query
        $sql = "SELECT 
                t.ticket_id,
                t.number AS ticket_number,
                CONCAT(u.name) AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_staff,
                ts.name AS status_name,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            WHERE t.topic_id = " . db_input($topic_id) . "
            ORDER BY t.created DESC
            LIMIT 100";

        $tickets = array();
        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                $tickets[] = array(
                    'ticket_id' => $row['ticket_id'],
                    'ticket_number' => $row['ticket_number'],
                    'user' => $row['user_name'] ?: __('Guest'),
                    'assigned_staff' => $row['assigned_staff'] ?: __('Unassigned'),
                    'status' => $row['status_name'],
                    'created' => Format::datetime($row['created'])
                );
            }
        }

        return $this->json_encode(array(
            'topic' => $topic->getFullName(),
            'tickets' => $tickets,
            'count' => count($tickets)
        ));
    }

    /**
     * Get all agents/staff list
     */
    function getAgents() {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $agents = Staff::getStaffMembers();
        
        $result = array();
        foreach ($agents as $id => $name) {
            $result[] = array(
                'id' => $id,
                'name' => (string) $name
            );
        }

        return $this->json_encode(array('agents' => $result));
    }

    /**
     * Get agent replies by staff ID
     */
    function getAgentReplies($staff_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$staff_id || !is_numeric($staff_id))
            Http::response(400, 'Invalid staff ID');

        $staff = Staff::lookup($staff_id);
        if (!$staff)
            Http::response(404, 'Agent not found');

        // Use the exact SQL query provided by the user
        $sql = "SELECT 
                e.id AS entry_id,
                t.number AS ticket_number,
                t.ticket_id,
                t.created AS ticket_created,
                tc.subject AS ticket_subject,
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                e.body AS reply_message,
                e.created AS reply_date
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            LEFT JOIN " . THREAD_TABLE . " th ON e.thread_id = th.id
            LEFT JOIN " . TICKET_TABLE . " t ON th.object_id = t.ticket_id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE e.type = 'R'
            AND e.staff_id = " . db_input($staff_id) . "
            ORDER BY e.created DESC
            LIMIT 100";

        $replies = array();
        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                // Clean up the reply body
                $body = Format::striptags($row['reply_message']);
                $body = Format::truncate($body, 150);
                
                $replies[] = array(
                    'entry_id' => $row['entry_id'],
                    'ticket_id' => $row['ticket_id'],
                    'ticket_number' => $row['ticket_number'],
                    'ticket_subject' => Format::truncate($row['ticket_subject'], 50),
                    'agent_name' => $row['agent_name'],
                    'reply_message' => $body,
                    'ticket_created' => Format::datetime($row['ticket_created']),
                    'reply_date' => Format::datetime($row['reply_date'])
                );
            }
        }

        return $this->json_encode(array(
            'agent' => $staff->getName()->getOriginal(),
            'replies' => $replies,
            'count' => count($replies)
        ));
    }

    /**
     * Export tickets by department as CSV
     */
    function exportDepartmentCSV($dept_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$dept_id || !is_numeric($dept_id))
            Http::response(400, 'Invalid department ID');

        $dept = Dept::lookup($dept_id);
        if (!$dept)
            Http::response(404, 'Department not found');

        $filename = sprintf('department-%s-tickets-%s.csv', 
            Format::slugify($dept->getName()), date('Ymd'));

        $sql = "SELECT 
                t.number AS ticket_number,
                u.name AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_agent,
                ts.name AS status_name,
                tc.subject,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE t.dept_id = " . db_input($dept_id) . "
            ORDER BY t.created DESC";

        $headers = array(
            __('Ticket Number'),
            __('User'),
            __('Assigned Agent'),
            __('Status'),
            __('Subject'),
            __('Created Date')
        );

        Http::download($filename, 'text/csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $headers);

        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                fputcsv($output, array(
                    $row['ticket_number'],
                    $row['user_name'] ?: __('Guest'),
                    $row['assigned_agent'] ?: __('Unassigned'),
                    $row['status_name'],
                    $row['subject'],
                    Format::datetime($row['created'])
                ));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export tickets by help topic as CSV
     */
    function exportTopicCSV($topic_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$topic_id || !is_numeric($topic_id))
            Http::response(400, 'Invalid topic ID');

        $topic = Topic::lookup($topic_id);
        if (!$topic)
            Http::response(404, 'Help topic not found');

        $filename = sprintf('topic-%s-tickets-%s.csv', 
            Format::slugify($topic->getName()), date('Ymd'));

        $sql = "SELECT 
                t.number AS ticket_number,
                u.name AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_staff,
                ts.name AS status_name,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            WHERE t.topic_id = " . db_input($topic_id) . "
            ORDER BY t.created DESC";

        $headers = array(
            __('Ticket Number'),
            __('User'),
            __('Assigned Staff'),
            __('Status'),
            __('Created Date')
        );

        Http::download($filename, 'text/csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $headers);

        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                fputcsv($output, array(
                    $row['ticket_number'],
                    $row['user_name'] ?: __('Guest'),
                    $row['assigned_staff'] ?: __('Unassigned'),
                    $row['status_name'],
                    Format::datetime($row['created'])
                ));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export agent replies as CSV
     */
    function exportAgentCSV($staff_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$staff_id || !is_numeric($staff_id))
            Http::response(400, 'Invalid staff ID');

        $staff = Staff::lookup($staff_id);
        if (!$staff)
            Http::response(404, 'Agent not found');

        $filename = sprintf('agent-%s-replies-%s.csv', 
            Format::slugify($staff->getName()->getOriginal()), date('Ymd'));

        $sql = "SELECT 
                e.id AS entry_id,
                t.number AS ticket_number,
                t.created AS ticket_created,
                tc.subject AS ticket_subject,
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                e.body AS reply_message,
                e.created AS reply_date
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            LEFT JOIN " . THREAD_TABLE . " th ON e.thread_id = th.id
            LEFT JOIN " . TICKET_TABLE . " t ON th.object_id = t.ticket_id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE e.type = 'R'
            AND e.staff_id = " . db_input($staff_id) . "
            ORDER BY e.created DESC";

        $headers = array(
            __('Entry ID'),
            __('Ticket #'),
            __('Agent'),
            __('Subject'),
            __('Reply Message'),
            __('Ticket Creation Date'),
            __('Reply Date')
        );

        Http::download($filename, 'text/csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $headers);

        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                $body = Format::striptags($row['reply_message']);
                fputcsv($output, array(
                    $row['entry_id'],
                    $row['ticket_number'],
                    $row['agent_name'],
                    $row['ticket_subject'],
                    $body,
                    Format::datetime($row['ticket_created']),
                    Format::datetime($row['reply_date'])
                ));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export tickets by department as PDF
     */
    function exportDepartmentPDF($dept_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$dept_id || !is_numeric($dept_id))
            Http::response(400, 'Invalid department ID');

        $dept = Dept::lookup($dept_id);
        if (!$dept)
            Http::response(404, 'Department not found');

        require_once(INCLUDE_DIR . 'class.pdf.php');

        $filename = sprintf('department-%s-tickets-%s.pdf', 
            Format::slugify($dept->getName()), date('Ymd'));

        $sql = "SELECT 
                t.number AS ticket_number,
                u.name AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_agent,
                ts.name AS status_name,
                tc.subject,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE t.dept_id = " . db_input($dept_id) . "
            ORDER BY t.created DESC
            LIMIT 500";

        $html = $this->buildPDFTable(
            sprintf(__('Department: %s - Tickets Report'), $dept->getName()),
            array(__('Ticket #'), __('User'), __('Agent'), __('Status'), __('Subject'), __('Created')),
            $sql,
            array('ticket_number', 'user_name', 'assigned_agent', 'status_name', 'subject', 'created')
        );

        $this->outputPDF($html, $filename);
    }

    /**
     * Export tickets by help topic as PDF
     */
    function exportTopicPDF($topic_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$topic_id || !is_numeric($topic_id))
            Http::response(400, 'Invalid topic ID');

        $topic = Topic::lookup($topic_id);
        if (!$topic)
            Http::response(404, 'Help topic not found');

        require_once(INCLUDE_DIR . 'class.pdf.php');

        $filename = sprintf('topic-%s-tickets-%s.pdf', 
            Format::slugify($topic->getName()), date('Ymd'));

        $sql = "SELECT 
                t.number AS ticket_number,
                u.name AS user_name,
                CONCAT(s.firstname, ' ', s.lastname) AS assigned_staff,
                ts.name AS status_name,
                t.created
            FROM " . TICKET_TABLE . " t
            LEFT JOIN " . USER_TABLE . " u ON t.user_id = u.id
            LEFT JOIN " . STAFF_TABLE . " s ON t.staff_id = s.staff_id
            LEFT JOIN " . TICKET_STATUS_TABLE . " ts ON t.status_id = ts.id
            WHERE t.topic_id = " . db_input($topic_id) . "
            ORDER BY t.created DESC
            LIMIT 500";

        $html = $this->buildPDFTable(
            sprintf(__('Help Topic: %s - Tickets Report'), $topic->getFullName()),
            array(__('Ticket #'), __('User'), __('Assigned Staff'), __('Status'), __('Created')),
            $sql,
            array('ticket_number', 'user_name', 'assigned_staff', 'status_name', 'created')
        );

        $this->outputPDF($html, $filename);
    }

    /**
     * Export agent replies as PDF
     */
    function exportAgentPDF($staff_id) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        if (!$staff_id || !is_numeric($staff_id))
            Http::response(400, 'Invalid staff ID');

        $staff = Staff::lookup($staff_id);
        if (!$staff)
            Http::response(404, 'Agent not found');

        require_once(INCLUDE_DIR . 'class.pdf.php');

        $filename = sprintf('agent-%s-replies-%s.pdf', 
            Format::slugify($staff->getName()->getOriginal()), date('Ymd'));

        $sql = "SELECT 
                e.id AS entry_id,
                t.number AS ticket_number,
                t.created AS ticket_created,
                tc.subject AS ticket_subject,
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                SUBSTRING(e.body, 1, 200) AS reply_message,
                e.created AS reply_date
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            LEFT JOIN " . THREAD_TABLE . " th ON e.thread_id = th.id
            LEFT JOIN " . TICKET_TABLE . " t ON th.object_id = t.ticket_id
            LEFT JOIN " . TICKET_CDATA_TABLE . " tc ON t.ticket_id = tc.ticket_id
            WHERE e.type = 'R'
            AND e.staff_id = " . db_input($staff_id) . "
            ORDER BY e.created DESC
            LIMIT 500";

        $html = $this->buildPDFTable(
            sprintf(__('Agent: %s - Replies Report'), $staff->getName()->getOriginal()),
            array(__('Entry ID'), __('Ticket #'), __('Agent'), __('Subject'), __('Reply Message'), __('Ticket Creation Date'), __('Reply Date')),
            $sql,
            array('entry_id', 'ticket_number', 'agent_name', 'ticket_subject', 'reply_message', 'ticket_created', 'reply_date'),
            true // Strip HTML from reply
        );

        $this->outputPDF($html, $filename);
    }

    /**
     * Get agent reply report by period
     */
    function getAgentReplyReport($period) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $where = "e.type = 'R'";
        switch ($period) {
            case 'daily':
                $where .= " AND DATE(e.created) = CURDATE()";
                $period_name = __('Today');
                break;
            case 'weekly':
                $where .= " AND YEARWEEK(e.created, 1) = YEARWEEK(CURDATE(), 1)";
                $period_name = __('This Week');
                break;
            case 'monthly':
                $where .= " AND MONTH(e.created) = MONTH(CURDATE()) AND YEAR(e.created) = YEAR(CURDATE())";
                $period_name = __('This Month');
                break;
            case 'yearly':
                $where .= " AND YEAR(e.created) = YEAR(CURDATE())";
                $period_name = __('This Year');
                break;
            default:
                Http::response(400, 'Invalid period');
        }

        $sql = "SELECT 
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                COUNT(e.id) AS reply_count,
                MAX(e.created) AS last_reply
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            WHERE " . $where . "
            GROUP BY e.staff_id
            ORDER BY reply_count DESC";

        $report = array();
        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                $report[] = array(
                    'agent' => $row['agent_name'] ?: __('Unknown'),
                    'replies' => $row['reply_count'],
                    'last_reply' => Format::datetime($row['last_reply'])
                );
            }
        }

        return $this->json_encode(array(
            'period' => $period_name,
            'report' => $report,
            'count' => count($report)
        ));
    }

    /**
     * Export agent reply report as CSV
     */
    function exportAgentReplyReportCSV($period) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $where = "e.type = 'R'";
        $period_slug = $period;
        switch ($period) {
            case 'daily': $where .= " AND DATE(e.created) = CURDATE()"; break;
            case 'weekly': $where .= " AND YEARWEEK(e.created, 1) = YEARWEEK(CURDATE(), 1)"; break;
            case 'monthly': $where .= " AND MONTH(e.created) = MONTH(CURDATE()) AND YEAR(e.created) = YEAR(CURDATE())"; break;
            case 'yearly': $where .= " AND YEAR(e.created) = YEAR(CURDATE())"; break;
        }

        $filename = sprintf('agent-replies-%s-%s.csv', $period_slug, date('Ymd'));
        
        $sql = "SELECT 
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                COUNT(e.id) AS reply_count,
                MAX(e.created) AS last_reply
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            WHERE " . $where . "
            GROUP BY e.staff_id
            ORDER BY reply_count DESC";

        $headers = array(__('Agent'), __('Replies'), __('Last Reply Date'));

        Http::download($filename, 'text/csv');
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $headers);

        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                fputcsv($output, array(
                    $row['agent_name'] ?: __('Unknown'),
                    $row['reply_count'],
                    Format::datetime($row['last_reply'])
                ));
            }
        }
        fclose($output);
        exit;
    }

    /**
     * Export agent reply report as PDF
     */
    function exportAgentReplyReportPDF($period) {
        global $thisstaff;

        if (!$thisstaff)
            Http::response(403, 'Access denied');

        $where = "e.type = 'R'";
        $period_name = '';
        switch ($period) {
            case 'daily': $where .= " AND DATE(e.created) = CURDATE()"; $period_name = __('Today'); break;
            case 'weekly': $where .= " AND YEARWEEK(e.created, 1) = YEARWEEK(CURDATE(), 1)"; $period_name = __('This Week'); break;
            case 'monthly': $where .= " AND MONTH(e.created) = MONTH(CURDATE()) AND YEAR(e.created) = YEAR(CURDATE())"; $period_name = __('This Month'); break;
            case 'yearly': $where .= " AND YEAR(e.created) = YEAR(CURDATE())"; $period_name = __('This Year'); break;
        }

        require_once(INCLUDE_DIR . 'class.pdf.php');
        $filename = sprintf('agent-replies-%s-%s.pdf', $period, date('Ymd'));

        $sql = "SELECT 
                CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                COUNT(e.id) AS reply_count,
                MAX(e.created) AS last_reply
            FROM " . THREAD_ENTRY_TABLE . " e
            LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
            WHERE " . $where . "
            GROUP BY e.staff_id
            ORDER BY reply_count DESC";

        $html = $this->buildPDFTable(
            sprintf(__('Agent Replies Report - %s'), $period_name),
            array(__('Agent'), __('Replies'), __('Last Reply')),
            $sql,
            array('agent_name', 'reply_count', 'last_reply')
        );

        $this->outputPDF($html, $filename);
    }

    /**
     * Helper: Build PDF table HTML
     */
    private function buildPDFTable($title, $headers, $sql, $fields, $stripHtml = false) {
        $html = '
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
            h1 { font-size: 16px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #007bff; color: white; padding: 8px; text-align: left; font-weight: bold; }
            td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .generated { color: #666; font-size: 9px; margin-top: 20px; }
        </style>
        <h1>' . Format::htmlchars($title) . '</h1>
        <table>
            <thead>
                <tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . Format::htmlchars($header) . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';

        if (($res = db_query($sql)) && db_num_rows($res)) {
            while ($row = db_fetch_array($res)) {
                $html .= '<tr>';
                foreach ($fields as $field) {
                    $value = isset($row[$field]) ? $row[$field] : '';
                    if ($stripHtml && $field === 'reply_message') {
                        $value = Format::striptags($value);
                    }
                    if ($field === 'created' || $field === 'reply_date') {
                        $value = Format::datetime($value);
                    }
                    $html .= '<td>' . Format::htmlchars($value ?: '-') . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        $html .= '<p class="generated">Generated: ' . Format::datetime(Misc::gmtime()) . '</p>';

        return $html;
    }

    /**
     * Helper: Output PDF
     */
    private function outputPDF($html, $filename) {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L', // Landscape for better table display
            'tempDir' => sys_get_temp_dir(),
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 10,
            'margin_right' => 10
        ]);

        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, 'D'); // D = Download
        exit;
    }
}

