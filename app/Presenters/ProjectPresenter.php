<?php

namespace App\Presenters;

class ProjectPresenter extends Presenter
{
    public function status(): string
    {
        return trans('projects.'.$this->model->status);
    }

    public function dates(): string
    {
        return $this->model->start_date->format('d M Y').' / '.$this->model->end_date->format('d M Y');
    }
}
