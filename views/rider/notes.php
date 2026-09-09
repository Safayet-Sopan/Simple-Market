<h1>Delivery Notes</h1>

<?php if (!$rider_id): ?>
    <p class="error">Your rider profile is missing. Contact an administrator.</p>
<?php else: ?>

    <p class="notice">Private to you — gate codes, landmarks, anything worth remembering
        for the next run.</p>

    <?php if ($successMsg): ?>
        <p class="success"><?php echo e($successMsg); ?></p>
    <?php endif; ?>
    <?php if ($actionErr): ?>
        <p class="error"><?php echo e($actionErr); ?></p>
    <?php endif; ?>

    <h2><?php echo $editing_note ? 'Edit Note' : 'New Note'; ?></h2>

    <form class="form-card" id="note-form" method="POST" action="<?php echo url('rider', 'notes'); ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="note_id" value="<?php echo e($editing_note['note_id'] ?? ''); ?>">

        <label>Title</label>
        <input class="field" type="text" name="title" maxlength="120"
               data-label="Title" data-required data-maxlength="120"
               value="<?php echo e($title); ?>">
        <span class="error"><?php echo e($titleErr); ?></span>

        <label>Note</label>
        <textarea class="field" name="body" rows="4"
                  data-label="Note" data-maxlength="2000"><?php echo e($body); ?></textarea>
        <span class="error"><?php echo e($bodyErr); ?></span>

        <label>Pin to Order # (optional)</label>
        <input class="field" type="text" name="order_id"
               data-label="Order number" data-integer
               value="<?php echo e($order_id); ?>">
        <span class="error"><?php echo e($orderErr); ?></span>

        <button class="btn" type="submit" name="save_note" value="1">
            <?php echo $editing_note ? 'Save Changes' : 'Add Note'; ?>
        </button>
        <?php if ($editing_note): ?>
            <a class="nav-link" href="<?php echo url('rider', 'notes'); ?>">Cancel</a>
        <?php endif; ?>
    </form>

    <h2>Your Notes</h2>

    <form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
        <input type="hidden" name="page" value="rider">
        <input type="hidden" name="action" value="notes">
        <input class="field" type="text" name="q" placeholder="Search notes or order #"
               value="<?php echo e($keyword); ?>">
        <button class="btn" type="submit">Search</button>
        <a class="nav-link" href="<?php echo url('rider', 'notes'); ?>">Clear</a>
    </form>

    <?php if (empty($notes)): ?>
        <p><?php echo $keyword !== '' ? 'No notes match that search.' : 'No notes yet.'; ?></p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Title</th>
                <th>Note</th>
                <th>Order</th>
                <th>Written</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($notes as $n): ?>
                <tr>
                    <td><?php echo e($n['title']); ?></td>
                    <td><?php echo nl2br(e($n['body'])); ?></td>
                    <td><?php echo $n['order_id'] ? '#' . (int) $n['order_id'] : '—'; ?></td>
                    <td><?php echo e($n['created_at']); ?></td>
                    <td>
                        <a href="<?php echo url('rider', 'notes', ['edit' => $n['note_id']]); ?>">Edit</a>
                        <form class="action-form" method="POST" action="<?php echo url('rider', 'notes'); ?>"
                              onsubmit="return confirm('Delete this note?');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="delete_note" value="<?php echo $n['note_id']; ?>">
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'deliveries'); ?>">My Deliveries</a>
    <a class="nav-link" href="<?php echo url('rider', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
