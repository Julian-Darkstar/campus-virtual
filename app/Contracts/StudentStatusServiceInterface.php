<?php
namespace App\Contracts;
use App\Models\User;
interface StudentStatusServiceInterface { public function resolve(User $user): array; }
