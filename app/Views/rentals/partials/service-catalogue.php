<?php $servicesList=is_array($services??null)?$services:[]; ?>
<div class="rentals-service-catalogue__intro">
  <div><p class="rentals-kicker">Production &amp; event support</p><h2>A team around your production.</h2><p>Service scope, crew and pricing are confirmed through a quotation after your request is reviewed.</p></div>
  <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/service-requests')) ?>">My service requests</a>
</div>
<?php if($servicesList!==[]): ?>
  <div class="rentals-service-catalogue__grid">
    <?php foreach($servicesList as $service):
      $name=(string)($service['name']??'Production service');
      $description=trim((string)($service['description']??''));
      $ideal=trim((string)($service['ideal_for']??''));
      $rate=(float)($service['rental_rate']??0);
      $unit=trim((string)($service['rental_unit']??''));
      $available=empty($service['is_sample']) && (int)($service['db_id']??0)>0 && in_array((string)($service['availability_status']??''),['available','inquire'],true);
    ?>
      <article class="rentals-item-card rentals-item-card--service" data-rentals-item data-category="<?= e_attr((string)($service['category']??'')) ?>">
        <div class="rentals-item-card__image"><?= $this->partial('rentals.partials.image',['image_path'=>$service['image_path']??null,'name'=>$name,'imageLabel'=>'Service']) ?></div>
        <div class="rentals-item-card__body">
          <span class="rentals-card__meta">Service<?= !empty($service['category_name'])?' / '.e((string)$service['category_name']):'' ?></span>
          <h3><?= e($name) ?></h3>
          <?php if($description!==''): ?><p class="rentals-item-card__description"><?= e($description) ?></p><?php endif ?>
          <?php if($ideal!==''): ?><p class="rentals-item-card__ideal"><strong>Ideal for</strong> <?= e($ideal) ?></p><?php endif ?>
          <div class="rentals-service-catalogue__rate"><span><?= $rate>0?'Listed service rate':'Quotation' ?></span><strong><?= $rate>0?'₱'.e(number_format($rate,2)).($unit!==''?' / '.e($unit):''):'Based on your requirements' ?></strong></div>
          <p class="rentals-service-catalogue__note">Share your event dates, venue and support requirements.</p>
          <?php if($available): ?><a class="rentals-btn rentals-btn--dark rentals-item-card__detail" href="<?= e_attr(url('/rentals/services/'.(int)$service['db_id'].'/request')) ?>">Request service <span aria-hidden="true">→</span></a>
          <?php else: ?><a class="rentals-btn rentals-btn--dark rentals-item-card__detail" href="<?= e_attr(url('rentals/support')) ?>">Ask about availability <span aria-hidden="true">→</span></a><?php endif ?>
        </div>
      </article>
    <?php endforeach ?>
  </div>
<?php else: ?>
  <div class="rentals-support-panel"><p class="rentals-card__meta">Services</p><h3><?= !empty($catalogUnavailable) ? 'Services are temporarily unavailable.' : (!empty($filteredEmpty)?'No matching services.':'No services are currently listed.') ?></h3><p><?= !empty($filteredEmpty)?'Try another search or category.':'Contact the 3AM team to discuss production and technical support requirements.' ?></p><a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/support')) ?>">Discuss support needs</a></div>
<?php endif ?>
