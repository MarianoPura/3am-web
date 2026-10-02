<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Add Category — Rentals Admin<?php $this->end() ?>
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
        <p class="rentals-card__meta">New record</p>
        <h2>Add category</h2>
      </div>
      <form method="post" enctype="multipart/form-data" action="<?= e_attr(url('rentals/admin/categories')) ?>" class="rentals-admin__form">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Basic information</legend>
          <label>Name
            <input name="name" maxlength="120" required placeholder="e.g. Cameras & Optics">
          </label>
          <label>Slug (optional)
            <input name="slug" maxlength="150" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="e.g. cameras-optics">
            <small>Used in category URL. Leave blank to generate automatically from name.</small>
          </label>
        </fieldset>
        <fieldset>
          <legend>Category details</legend>
          <label>Description
            <textarea name="description" rows="3" placeholder="Category summary..."></textarea>
          </label>
          <label>Upload category image (optional)
            <input type="file" name="category_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG or WebP. Maximum <?= e(number_format(\App\Services\RentalManagedImage::maxUploadBytes() / 1048576, 1)) ?> MB.</small>
          </label>
        </fieldset>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Add category</button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
