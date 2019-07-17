<?php

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Cronschedules Model
 *
 * @property \App\Model\Table\CronjobsTable|\Cake\ORM\Association\BelongsTo $Cronjobs
 *
 * @method \App\Model\Entity\Cronschedule get($primaryKey, $options = [])
 * @method \App\Model\Entity\Cronschedule newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Cronschedule[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Cronschedule|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Cronschedule|bool saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Cronschedule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Cronschedule[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Cronschedule findOrCreate($search, callable $callback = null, $options = [])
 */
class CronschedulesTable extends Table {

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setTable('cronschedules');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Cronjobs', [
            'foreignKey' => 'cronjob_id'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator) {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('is_running')
            ->allowEmptyString('is_running');

        $validator
            ->dateTime('start_time')
            ->requirePresence('start_time', 'create')
            ->allowEmptyDateTime('start_time', null, false);

        $validator
            ->dateTime('end_time')
            ->requirePresence('end_time', 'create')
            ->allowEmptyDateTime('end_time', null, false);

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules) {
        $rules->add($rules->existsIn(['cronjob_id'], 'Cronjobs'));

        return $rules;
    }
}
