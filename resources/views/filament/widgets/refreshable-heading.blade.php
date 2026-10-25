<span class="flex items-center gap-2">
    {{ $heading }}
    <x-filament::icon-button icon="heroicon-m-arrow-path" color="gray" size="sm" wire:click="$refresh"
        label="Actualizar desde la base de datos" tooltip="Actualizar" />
</span>
