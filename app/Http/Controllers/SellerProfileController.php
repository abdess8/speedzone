<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSellerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class SellerProfileController extends Controller
{
    /**
     * Documents that live in a single column each and replace their predecessor
     * when a new file is uploaded.
     *
     * @var array<int, string>
     */
    private const DOCUMENTS = [
        'rib_attachment',
        'cin_front_attachment',
        'cin_back_attachment',
    ];

    /**
     * Save the identity, pickup and banking details a vendor fills in himself.
     */
    public function update(UpdateSellerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(self::DOCUMENTS);

        foreach (self::DOCUMENTS as $document) {
            if (! $request->hasFile($document)) {
                // Absent from the payload means "leave it alone", never "erase".
                continue;
            }

            if ($user->{$document}) {
                Storage::disk('public')->delete($user->{$document});
            }

            $data[$document] = $request->file($document)->store('users/billing', 'public');
        }

        $user->update($data);

        return back()->with('success', __('profile.seller_details.saved'));
    }

    /**
     * Drop one of the uploaded documents.
     */
    public function destroyDocument(UpdateSellerProfileRequest $request, string $document): RedirectResponse
    {
        abort_unless(in_array($document, self::DOCUMENTS, true), 404);

        $user = $request->user();

        if ($user->{$document}) {
            Storage::disk('public')->delete($user->{$document});
            $user->update([$document => null]);
        }

        return back()->with('success', __('profile.seller_details.document_removed'));
    }
}
