<?php $recordId = (int) ($record['id'] ?? 0); ?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?><?= $recordId > 0 ? 'Edit' : 'Add' ?> Category — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Categories</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/categories')) ?>">← Back to categories</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta"><?= $recordId > 0 ? 'Existing record' : 'New record' ?></p>
        <h2><?= $recordId > 0 ? 'Edit ' . e((string) ($record['name'] ?? 'record')) : 'Add category' ?></h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/categories')) ?>" class="rentals-admin__form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e_attr((string) $recordId) ?>">
        <fieldset><legend>Basic information</legend>
          <label>Name<input name="name" maxlength="120" required value="<?= e_attr((string) ($record['name'] ?? '')) ?>"></label>
          <label>Slug<input name="slug" maxlength="150" pattern="[a-z0-9]+(-[a-z0-9]+)*" required value="<?= e_attr((string) ($record['slug'] ?? '')) ?>"></label>
        </fieldset>
        <fieldset><legend>Category details</legend>
          <label>Description<textarea name="description" rows="3"><?= e((string) ($record['description'] ?? '')) ?></textarea></label>
          <label>Existing local image path<input name="image_path" list="rental-images" value="<?= e_attr((string) ($record['image_path'] ?? '')) ?>"></label>
        </fieldset>
        <fieldset><legend>Status</legend><label>Record status<select name="is_active"><option value="1"<?= (int) ($record['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($record['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></label></fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit"><?= $recordId > 0 ? 'Save changes' : 'Add category' ?></button>
      </form>
    </div>
    <?php if ($images !== []): ?><datalist id="rental-images"><?php foreach ($images as $image): ?><option value="<?= e_attr($image) ?>"></option><?php endforeach ?></datalist><?php endif ?>
  </div>
</section>
<?php $this->end() ?>
