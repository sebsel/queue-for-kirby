<style>
.btn-small{
  padding: .2em .75em;
  display: block;
  margin-bottom: .2em;
}

.buttons {
  position: absolute;
  top: 0;
  right: .3em;
}

.normal-white-space {
  white-space: normal;
  position: relative;
}
</style>

<div class="dashboard-box">
  <ul class="dashboard-items">

    <?php foreach ($failed as $job): ?>

    <li class="dashboard-item">
        <figure class="normal-white-space">
          <figcaption class="dashboard-item-text " style="
            width: calc(100% - 3em);
            padding-top: 0.5em;
          ">
            <strong><?= $job->name() ?></strong><br>
            <em><?= $job->error() ?></em>
          </figcaption>

          <div class="buttons">
            <a class="btn btn-rounded btn-small"
              href="<?=url('/panel/queue/retry/'.$job->id()) ?>" target>
              <i class="icon fa fa-refresh"></i>
            </a>

            <a class="btn btn-rounded btn-negative btn-small"
              href="<?=url('/panel/queue/remove/'.$job->id()) ?>" target>
              <i class="icon fa fa-remove"></i>
            </a>
          </div>
        </figure>



        <div class="dashboard-item-text" style="
            margin: 0.75em;
          ">
            <?php if (is_array($job->data())) foreach ($job->data() as $key => $value): ?>
              <strong><?= $key ?></strong>:&nbsp;<code><?=$value?></code><br>
            <?php endforeach ?>

          </div>
    </li>
    <?php endforeach ?>

  </ul>
</div>
