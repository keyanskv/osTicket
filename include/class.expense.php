<?php
class TicketExpense extends VerySimpleModel {
    static $meta = array(
        'table' => TABLE_PREFIX . 'ticket_expense',
        'pk' => array('id'),
        'joins' => array(
            'ticket' => array(
                'constraint' => array('ticket_id' => 'Ticket.ticket_id'),
            ),
            'staff' => array(
                'constraint' => array('staff_id' => 'Staff.staff_id'),
            ),
        ),
    );

    function getId() { return $this->id; }
    function getTicketId() { return $this->ticket_id; }
    function getStaffId() { return $this->staff_id; }
    function getAmount() { return $this->amount; }
    function getDescription() { return $this->description; }
    function getCreateDate() { return $this->created; }

    static function create($vars, &$errors) {
        if (!$vars['ticket_id'])
            $errors['err'] = 'Ticket ID is required';
        if (!$vars['amount'] || !is_numeric($vars['amount']))
            $errors['amount'] = 'Valid amount is required';
        if (!$vars['description'])
            $errors['description'] = 'Description is required';

        if ($errors) return false;

        $expense = new static(array(
            'ticket_id' => $vars['ticket_id'],
            'staff_id' => $vars['staff_id'],
            'amount' => $vars['amount'],
            'description' => $vars['description'],
            'created' => new SqlFunction('NOW'),
        ));

        if ($expense->save())
            return $expense;

        return false;
    }

    static function getForTicket($ticket_id) {
        return static::objects()->filter(array('ticket_id' => $ticket_id))->order_by('-created');
    }
}
?>
