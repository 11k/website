<?
use Destiny\Common\Utils\Tpl;
use Destiny\Common\Utils\Date;
if (! empty ( $model->articles )) :
?>
<section class="container">
	<div class="content content-dark content-split clearfix row-no-padding">

		<div class="media-block col-sm-6 stream">
			<h3 class="title">
				<span>Blog</span> <a href="http://blog.destiny.gg">destiny.gg</a>
			</h3>
			<div class="entries">
				<?php for ($i=0; $i<3; ++$i):?>
				<?php $article = $model->articles[$i] ?>
				<div class="media">
					<div class="media-body">
						<div class="media-heading">
							<a href="<?=$article['permalink']?>"><?=$article['title']?></a>
						</div>
						<div>
							<?php if(count($article['categories']) > 0): ?>
							<small>
								Posted in 
								<?= join(", ", $article['categories']); ?>
							</small> 
							<?php endif; ?>
						</div>
						<?=Tpl::moment(Date::getDateTime($article['date']),Date::FORMAT)?>
					</div>
				</div>
				<?endfor;?>
			</div>
		</div>

		<div class="media-block col-sm-6 stream">
			<h3 class="title">&nbsp;</h3>
			<div class="entries">
				<?php for ($i=3; $i<6; ++$i):?>
				<?php $article = $model->articles[$i] ?>
				<div class="media">
					<div class="media-body">
						<div class="media-heading">
							<a href="<?=$article['permalink']?>"><?=$article['title']?></a>
						</div>
						<div>
							<?php if(count($article['categories']) > 0): ?>
							<small>
								Posted in 
								<?= join(", ", $article['categories']); ?>
							</small> 
							<?php endif; ?>
						</div>
						<?=Tpl::moment(Date::getDateTime($article['date']),Date::FORMAT)?>
					</div>
				</div>
				<?endfor;?>
			</div>
		</div>

	</div>
</section>
<?endif;?>
