<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\BootstrapPluginAsset;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\Pjax;

BootstrapPluginAsset::register($this);

$this->title = 'Produkty';
?>
<div class="site-index">
    <div class="mt-4 mb-3 d-flex align-items-center justify-content-between">
        <h1 class="h3 mb-0">Výpis produktů</h1>
    </div>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'name:text:Název',
            'code:text:Kód',
            [
                'attribute' => 'url',
                'format' => 'raw',
                'label' => 'URL',
                'value' => function ($model) {
                    /* @var $model app\models\Product */
                    return $model->url ? Html::a('Odkaz', $model->url, ['target' => '_blank', 'rel' => 'noopener']) : '';
                }
            ],
            [
                    'attribute' => 'image_url',
                    'format' => 'raw',
                    'label' => 'Obrázek',
                    'value' => function ($model) {
                        /* @var $model app\models\Product */
                        return $model->image_url ? Html::img($model->image_url, ['style' => 'width:60px;height:auto;border-radius:4px;']) : '';
                    },
                    'contentOptions' => ['style' => 'width:70px;'],
            ],
            [
                'attribute' => 'stock_qty',
                'label' => 'Sklad',
                'contentOptions' => function ($model) {
                    $qty = (int)$model->stock_qty;
                    $class = $qty > 0 ? 'text-success fw-semibold' : 'text-danger';
                    return ['class' => $class];
                },
                'value' => function ($model) { return $model->stock_qty; }
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{detail} {updateDesc}',
                'buttons' => [
                    'detail' => function ($url, $model) {
                        return Html::button('Detail', [
                            'class' => 'btn btn-sm btn-primary',
                            'onclick' => 'showDetail(' . (int)$model->id . ')'
                        ]);
                    },
                    'updateDesc' => function ($url, $model) {
                        return Html::button('Upravit popis', [
                            'class' => 'btn btn-sm btn-outline-secondary',
                            'onclick' => 'updateDescription(' . (int)$model->id . ', this)'
                        ]);
                    },
                ],
            ],
        ],
        'pager' => [
            'options' => ['class' => 'pagination justify-content-center'],
            'linkContainerOptions' => ['class' => 'page-item'],
            'linkOptions' => ['class' => 'page-link'],
        ],
    ]); ?>
    <?php Pjax::end(); ?>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail produktu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="detailBody">
            <div class="text-center text-muted">Načítám…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavřít</button>
      </div>
    </div>
  </div>
</div>

<?php
$detailUrl = Yii::$app->urlManager->createUrl(['site/detail']);
$updateUrl = Yii::$app->urlManager->createUrl(['site/update-description']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$vars = "var detailUrl = " . json_encode($detailUrl) . ";"
      . " var updateUrl = " . json_encode($updateUrl) . ";"
      . " var csrfParam = " . json_encode($csrfParam) . ";"
      . " var csrfToken = " . json_encode($csrfToken) . ";";
$this->registerJs($vars, \yii\web\View::POS_END);
$js = <<<'JS'
window.showDetail = function(id) {
  var modalEl = document.getElementById('detailModal');
  var body = document.getElementById('detailBody');
  body.innerHTML = '<div class="text-center text-muted">Načítám…</div>';
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
  var u = new URL(detailUrl, window.location.origin);
  u.searchParams.set('id', id);
  fetch(u.toString())
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data.success) throw new Error(data.error || 'Chyba');
      var d = data.data || {};
      var catsArr = Array.isArray(d.categories) ? d.categories.filter(Boolean) : [];
      var cats = catsArr.join(', ');
      var html = '';
      html += '<div class="d-flex gap-3">';
      if (d.imageUrl) {
        html += '<img src="' + d.imageUrl + '" style="width:120px;height:auto;border-radius:6px;"/>';
      }
      html += '<div>';
      html += '<div><strong>' + (d.name || '') + '</strong></div>';
      html += '<div class="text-muted">Kód: ' + (d.code || '') + '</div>';
      html += '<div class="mt-2">Cena: <strong>' + (d.price || '—') + '</strong></div>';
      html += '<div class="mt-2">Kategorie: <small>' + (cats || '—') + '</small></div>';
      if (d.url) {
        html += '<div class="mt-2"><a href="' + d.url + '" target="_blank" rel="noopener">Otevřít na e‑shopu</a></div>';
      }
      html += '</div>';
      html += '</div>';
      html += '<div class="mt-3"><small class="text-muted">Sklad: ' + (d.stock != null ? d.stock : '—') + '</small></div>';
      html += '<hr/>';
      html += '<div>' + (d.description || '') + '</div>';
      body.innerHTML = html;
    })
    .catch(function(err){
      console.error(err);
      body.innerHTML = '<div class="text-danger">Nepodařilo se načíst detail.</div>';
    });
}

window.updateDescription = function(id, btn) {
  if (!confirm('Přidat prefix "testFrantisek" k popisu?')) return;
  var formData = new FormData();
  formData.append(csrfParam, csrfToken);
  formData.append('id', id);
  btn.disabled = true;
  btn.innerText = 'Upravuji…';
  var u2 = new URL(updateUrl, window.location.origin);
  u2.searchParams.set('id', id);
  fetch(u2.toString(), { method: 'POST', body: formData })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data.success) throw new Error(data.error || 'Chyba');
      alert('Popis byl aktualizován.');
    })
    .catch(function(err){
      console.error(err);
      alert('Aktualizace selhala: ' + err.message);
    })
    .finally(function(){
      btn.disabled = false;
      btn.innerText = 'Upravit popis';
    });
}
JS;
$this->registerJs($js, \yii\web\View::POS_END);
?>
