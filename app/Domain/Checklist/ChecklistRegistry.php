<?php

namespace App\Domain\Checklist;

class ChecklistRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forCondition(string $conditionKey): array
    {
        $map = [
            'diabete' => [
                ['question_key' => 'glicemia_monitorata', 'label' => 'Monitoraggio glicemia regolare', 'input_type' => 'boolean', 'default_active' => true, 'sort_order' => 10],
                ['question_key' => 'aderenza_terapia', 'label' => 'Aderenza alla terapia antidiabetica', 'input_type' => 'select', 'options_json' => ['ottima', 'parziale', 'scarsa'], 'default_active' => true, 'sort_order' => 20],
                ['question_key' => 'note_eventi_ipoglicemia', 'label' => 'Eventuali episodi ipoglicemici', 'input_type' => 'text', 'default_active' => true, 'sort_order' => 30],
            ],
            'bpco' => [
                ['question_key' => 'dispnea_stabile', 'label' => 'Dispnea stabile rispetto all’ultimo controllo', 'input_type' => 'boolean', 'default_active' => true, 'sort_order' => 10],
                ['question_key' => 'uso_inalatore', 'label' => 'Corretto uso dell’inalatore', 'input_type' => 'select', 'options_json' => ['sempre', 'a_volte', 'mai'], 'default_active' => true, 'sort_order' => 20],
                ['question_key' => 'numero_riacutizzazioni', 'label' => 'Numero riacutizzazioni ultimo mese', 'input_type' => 'number', 'default_active' => true, 'sort_order' => 30],
            ],
            'dislipidemia' => [
                ['question_key' => 'aderenza_statina', 'label' => 'Aderenza alla terapia ipolipemizzante', 'input_type' => 'boolean', 'default_active' => true, 'sort_order' => 10],
                ['question_key' => 'controllo_lipidico', 'label' => 'Data ultimo controllo lipidico', 'input_type' => 'date', 'default_active' => true, 'sort_order' => 20],
                ['question_key' => 'note_stile_vita', 'label' => 'Note su stile di vita e alimentazione', 'input_type' => 'text', 'default_active' => true, 'sort_order' => 30],
            ],
            'ipertensione' => [
                ['question_key' => 'pressione_controllata', 'label' => 'Pressione arteriosa controllata', 'input_type' => 'boolean', 'default_active' => true, 'sort_order' => 10],
                ['question_key' => 'misurazioni_settimanali', 'label' => 'Numero misurazioni settimanali', 'input_type' => 'number', 'default_active' => true, 'sort_order' => 20],
                ['question_key' => 'aderenza_antipertensivi', 'label' => 'Aderenza terapia antipertensiva', 'input_type' => 'select', 'options_json' => ['ottima', 'parziale', 'scarsa'], 'default_active' => true, 'sort_order' => 30],
            ],
        ];

        return $map[$conditionKey] ?? [];
    }
}
