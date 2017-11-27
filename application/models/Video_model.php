<?php
class Video_model extends CI_Model {
    protected $table = 'videos';

    public function create($data) {
        $this->db->insert($this->table, $data);

        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $this->db->where('id', $id);
        $this->db->update($this->table, $data);
    }

    public function get($id) {
        $query = $this->db->get_where($this->table, ['id' => $id]);

        return count($query->result()) ? $query->result()[0] : null;
    }

    public function getFirstUnprocessedVideo() {
        $query = $this->db->get_where($this->table, ['status' => 'waiting']);

        return count($query->result()) ? $query->result()[0] : null;
    }


    public function getAll() {
        $query = $this->db->get($this->table);

        return $query->result();
    }
}
