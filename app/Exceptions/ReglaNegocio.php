<?php

namespace App\Exceptions;

/** Violación de una regla de negocio; se presenta al usuario como mensaje (HTTP 422). */
class ReglaNegocio extends \RuntimeException
{
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
