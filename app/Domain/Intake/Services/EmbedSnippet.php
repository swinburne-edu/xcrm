<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Models\Form;

/**
 * The HTML the university pastes into its website for a Form: the fields, a hidden honeypot, and a small script
 * that posts them (with the page's utm_* parameters) to POST /api/public/leads and shows the success message.
 */
class EmbedSnippet
{
    public static function for(Form $form, string $endpoint): string
    {
        $labels = Form::AVAILABLE_FIELDS;
        $rows = '';

        foreach ($form->fields as $field) {
            $key = $field['key'];
            $label = e($labels[$key] ?? $key);
            $required = ! empty($field['required']) ? ' required' : '';
            $input = $key === 'note'
                ? "<textarea name=\"{$key}\"{$required}></textarea>"
                : '<input name="'.$key.'" type="'.match ($key) {
                    'email' => 'email',
                    'phone' => 'tel',
                    'graduation_year' => 'number',
                    default => 'text',
                }."\"{$required}>";
            $rows .= "  <label>{$label}{$input}</label>\n";
        }

        $uuid = e($form->uuid);
        $message = json_encode($form->success_message ?: 'Cảm ơn bạn! Nhà trường sẽ liên hệ trong thời gian sớm nhất.', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
        $url = e($endpoint);

        return <<<HTML
<form id="xcrm-lead-{$uuid}" data-endpoint="{$url}">
{$rows}  <input type="text" name="hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
  <button type="submit">Đăng ký tư vấn</button>
  <p class="xcrm-msg" hidden></p>
</form>
<script>
(function () {
  var f = document.getElementById('xcrm-lead-{$uuid}');
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var data = Object.fromEntries(new FormData(f));
    data.form_uuid = '{$uuid}';
    var q = new URLSearchParams(location.search);
    ['source', 'medium', 'campaign', 'content'].forEach(function (k) { if (q.get('utm_' + k)) data['utm_' + k] = q.get('utm_' + k); });
    fetch(f.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        var m = f.querySelector('.xcrm-msg');
        m.hidden = false;
        m.textContent = res.ok ? {$message} : (res.j.message || 'Có lỗi xảy ra, vui lòng thử lại.');
        if (res.ok) f.reset();
      });
  });
})();
</script>
HTML;
    }
}
