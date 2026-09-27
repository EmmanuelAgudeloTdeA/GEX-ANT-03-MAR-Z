<?php

namespace App\Policies;

use App\Models\Category;
use Illuminate\Foundation\Auth\User as AuthUser;

class CategoryPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Category');
    }

    public function view(AuthUser $authUser, Category $category): bool
    {
        return $authUser->can('View:Category');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Category');
    }

    public function update(AuthUser $authUser, Category $category): bool
    {
        return $authUser->can('Update:Category');
    }

    // Las categorias no se borran, se desactivan: las solicitudes las referencian.
    public function delete(AuthUser $authUser, Category $category): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
