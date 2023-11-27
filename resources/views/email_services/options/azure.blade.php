<x-sendportal.text-field name="settings[azure_host]" :label="__('Azure Endpoint')" :value="Arr::get($settings ?? [], 'key')" autocomplete="off" />
<x-sendportal.text-field name="settings[key]" :label="__('Azure Access Key')" :value="Arr::get($settings ?? [], 'secret')" autocomplete="off" />
