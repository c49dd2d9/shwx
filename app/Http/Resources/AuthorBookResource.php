<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthorBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'bookname' => $this->bookname,
            'bookintro' => $this->bookintro,
            'bookimg' => $this->bookimg,
            'role' => $this->role,
            'classid' => $this->classid,
            'recomtext' => $this->recomtext
        ];
    }
}
