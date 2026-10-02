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
      <form method="post" enctype="multipart/form-data" action="<?= e_attr(url('rentals/admin/categories/' . $categoryId . '/edit')) ?>" class="rentals-admin__form">
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
          <label>Upload category image
            <input type="file" name="category_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG or WebP. Maximum <?= e(number_format(\App\Services\RentalManagedImage::maxUploadBytes() / 1048576, 1)) ?> MB. Leave empty to keep the current image.</small>
          </label>
          <?php $categoryImage = \App\Models\RentalCatalog::imagePath($category['image_path'] ?? null); if ($categoryImage !== null): ?>
            <img class="rentals-admin__image-preview" src="<?= e_attr(\App\Models\RentalCatalog::imageUrl($categoryImage)) ?>" alt="Current category image">
          <?php endif ?>
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
  </div>
</section>
<?php $this->end() ?>
