import api from './api'

/** Whiteboard API: the list never carries scene data; open a board to load it. */
export const listBoards = () => api.get('/whiteboards').then((r) => r.data)
export const createBoard = (title = null) => api.post('/whiteboards', { title }).then((r) => r.data)
export const getBoard = (id) => api.get(`/whiteboards/${id}`).then((r) => r.data)
export const saveBoard = (id, payload) => api.patch(`/whiteboards/${id}`, payload).then((r) => r.data)
export const deleteBoard = (id) => api.delete(`/whiteboards/${id}`)
