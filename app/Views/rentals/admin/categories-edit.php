<?php
$category = $category ?? ($record ?? []);
$categoryId = (int) ($category['id'] ?? 0);
?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Edit Category — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Categories</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?>
      <p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>>
        <?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?>
      </p>
    <?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/categories')) ?>">← Back to categories</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta">Existing record</p>
        <h2>Edit <?= e((string) ($category['name'] ?? 'Category')) ?></h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/categories/' . $categoryId . '/edit')) ?>" class="rentals-admin__form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e_attr((string) $categoryId) ?>">
        <fieldset>
          <legend>Basic information</legend>
          <label>Name
            <input name="name" maxlength="120" required value="<?= e_attr((string) ($category['name'] ?? '')) ?>">
          </label>
          <label>Slug
            <input name="slug" maxlength="150" pattern="[a-z0-9]+(-[a-z0-9]+)*" required value="<?= e_attr((string) ($category['slug'] ?? '')) ?>">
            <small>Used in category URL.</small>
          </label>
        </fieldset>
        <fieldset>
          <legend>Category details</legend>
          <label>Description
            <textarea name="description" rows="3"><?= e((string) ($category['description'] ?? '')) ?></textarea>
          </label>
          <label>Existing local image path
            <input name="image_path" list="rental-images" value="<?= e_attr((string) ($category['image_path'] ?? '')) ?>">
          </label>
        </fieldset>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1"<?= (int) ($category['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option>
              <option value="0"<?= (int) ($category['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Save changes</button>
      </form>
    </div>
    <?php if (($images ?? []) !== []): ?>
      <datalist id="rental-images">
        <?php foreach ($images as $image): ?>
          <option value="<?= e_attr($image) ?>"></option>
        <?php endforeach ?>
      </datalist>
    <?php endif ?>
  </div>
</section>
<?php $this->end() ?>
