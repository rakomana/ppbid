@props(['listing', 'size' => 'sm', 'label' => 'Share'])

@php
    $invoiceNumber = \App\Invoices\Invoice::numberFor($listing->id);
    $filename = "ppbid-{$invoiceNumber}.pdf";
@endphp

{{--
    "Share" for an invoice PDF: opens the phone's share sheet (WhatsApp, email, ...)
    with the PDF attached, using the browser's Web Share API. No WhatsApp integration.

    Browsers only open the share sheet straight after a tap, so the PDF is fetched as
    soon as the pointer reaches the button and shared on the tap. Where files can't
    be shared (most desktops), the PDF is downloaded instead to attach by hand.
--}}
<span
    x-data="{
        url: @js(route('listings.invoice', $listing)),
        filename: @js($filename),
        title: @js('Invoice '.$invoiceNumber),
        text: @js('ppbid invoice '.$invoiceNumber.' for “'.$listing->title.'”'),
        file: null,
        loading: null,
        busy: false,
        message: '',
        prepare() {
            if (this.file || this.loading) return this.loading;
            this.loading = fetch(this.url, { credentials: 'same-origin' })
                .then((response) => {
                    if (! response.ok) throw new Error('download failed');
                    return response.blob();
                })
                .then((blob) => {
                    this.file = new File([blob], this.filename, { type: 'application/pdf' });
                })
                .catch(() => { this.loading = null; });
            return this.loading;
        },
        canShareFiles() {
            try {
                return !! navigator.canShare && navigator.canShare({ files: [new File([''], this.filename, { type: 'application/pdf' })] });
            } catch (e) {
                return false;
            }
        },
        download() {
            const link = document.createElement('a');
            link.href = this.url;
            link.download = this.filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            this.message = 'Sharing files isn’t supported in this browser, so the PDF was downloaded. Attach it in WhatsApp.';
        },
        async share() {
            this.message = '';
            if (! this.canShareFiles()) return this.download();
            this.busy = true;
            await this.prepare();
            this.busy = false;
            if (! this.file) {
                this.message = 'The invoice couldn’t be loaded. Please try again.';
                return;
            }
            try {
                await navigator.share({ files: [this.file], title: this.title, text: this.text });
            } catch (error) {
                if (error.name === 'NotAllowedError') this.message = 'Tap Share again to open the share menu.';
                else if (error.name !== 'AbortError') this.message = 'The invoice couldn’t be shared. Please try again.';
            }
        },
    }"
    x-on:pointerenter="prepare()"
    x-on:pointerdown="prepare()"
    x-on:focusin="prepare()"
    class="inline-flex flex-col items-start gap-1"
    data-share-invoice
>
    @if ($label)
        <flux:button :size="$size" icon="share" x-on:click="share()" x-bind:disabled="busy" {{ $attributes }}>{{ $label }}</flux:button>
    @else
        <flux:button :size="$size" icon="share" x-on:click="share()" x-bind:disabled="busy" aria-label="Share invoice" {{ $attributes }} />
    @endif
    <span x-show="message" x-text="message" x-cloak class="max-w-56 text-xs text-zinc-500 dark:text-zinc-400" role="status"></span>
</span>
