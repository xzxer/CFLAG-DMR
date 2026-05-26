#!/usr/bin/env python
#
###############################################################################
#   Copyright (C) 2016-2019 Cortney T. Buffington, N0MJS <n0mjs@me.com>
#
#   This program is free software; you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation; either version 3 of the License, or
#   (at your option) any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program; if not, write to the Free Software Foundation,
#   Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301  USA
###############################################################################

'''
Patched bridge.py: DB-driven talkgroup routing via bridge_routes.json.

Instead of loading routing rules from rules.py at startup, this version reads
/hblink3/json/bridge_routes.json, which PHP writes whenever talkgroup
subscriptions or device status changes. A polling loop checks the file every
10 seconds and rebuilds BRIDGES atomically when the version counter changes.
The last-known-good routing table is preserved on parse errors.

UNIT calls default to all MASTER-mode systems (no rules.py needed).
All other behaviour is identical to upstream bridge.py.
'''

import sys
from bitarray import bitarray
from time import time
import json as _json
import os as _os

from twisted.internet.protocol import Factory, Protocol
from twisted.protocols.basic import NetstringReceiver
from twisted.internet import reactor, task

from hblink import HBSYSTEM, OPENBRIDGE, systems, hblink_handler, reportFactory, REPORT_OPCODES, mk_aliases
from dmr_utils3.utils import bytes_3, int_id, get_alias
from dmr_utils3 import decode, bptc, const
import config
import log
from const import *

import pickle
import logging
logger = logging.getLogger(__name__)

__author__     = 'Cortney T. Buffington, N0MJS'
__copyright__  = 'Copyright (c) 2016-2019 Cortney T. Buffington, N0MJS and the K0USY Group'
__credits__    = 'Colin Durbridge, G4EML, Steve Zingman, N4IRS; Mike Zingman, N4IRR; Jonathan Naylor, G4KLX; Hans Barthen, DL5DI; Torsten Shultze, DG1HT'
__license__    = 'GNU GPLv3'
__maintainer__ = 'Cort Buffington, N0MJS'
__email__      = 'n0mjs@me.com'

UNIT_MAP = {}

# ---------------------------------------------------------------------------
# DB-driven routing via bridge_routes.json
# ---------------------------------------------------------------------------

BRIDGE_ROUTES_JSON = '/hblink3/json/bridge_routes.json'

_routes_version   = -1
_routes_mtime     = 0.0
_routes_last_good = None   # last successfully built BRIDGES dict

BRIDGES = {}               # live routing table, updated by routes_reload_loop


def _make_bridges_safe(_rules):
    '''Build BRIDGES dict from raw JSON data without sys.exit on unknown systems.'''
    result = {}
    for _bridge in _rules:
        systems_list = []
        for _system in _rules[_bridge]:
            if _system['SYSTEM'] not in CONFIG['SYSTEMS']:
                logger.warning('(ROUTER) Bridge "%s" references unknown system "%s" — skipping', _bridge, _system['SYSTEM'])
                continue
            entry = dict(_system)
            entry['TGID'] = bytes_3(entry['TGID'])
            for i in range(len(entry['ON'])):
                entry['ON'][i] = bytes_3(entry['ON'][i])
            for i in range(len(entry['OFF'])):
                entry['OFF'][i] = bytes_3(entry['OFF'][i])
            entry['TIMEOUT'] = entry['TIMEOUT'] * 60
            if entry['ACTIVE']:
                entry['TIMER'] = time() + entry['TIMEOUT']
            else:
                entry['TIMER'] = time()
            systems_list.append(entry)
        if systems_list:
            result[_bridge] = systems_list
    return result


def _load_bridge_routes():
    global BRIDGES, _routes_version, _routes_mtime, _routes_last_good
    try:
        mtime = _os.path.getmtime(BRIDGE_ROUTES_JSON)
    except OSError:
        return  # file not yet written

    if mtime <= _routes_mtime:
        return  # not changed since last check

    try:
        with open(BRIDGE_ROUTES_JSON, 'r') as _f:
            data = _json.load(_f)
        new_version = data.get('version', -1)
        if new_version == _routes_version:
            _routes_mtime = mtime
            return  # same version, skip rebuild

        raw_bridges = data.get('bridges', {})
        new_bridges = _make_bridges_safe(raw_bridges)
        BRIDGES = new_bridges
        _routes_last_good = new_bridges
        _routes_version = new_version
        _routes_mtime = mtime
        logger.info('(ROUTER) Loaded bridge_routes.json: %d bridges, version %d', len(BRIDGES), _routes_version)
    except Exception as _e:
        logger.error('(ROUTER) Failed to parse bridge_routes.json: %s — keeping last-known-good routing', _e)
        if _routes_last_good is not None:
            BRIDGES = _routes_last_good


def routes_reload_loop():
    _load_bridge_routes()

# ---------------------------------------------------------------------------


def config_reports(_config, _factory):
    if True:
        def reporting_loop(logger, _server):
            logger.debug('(REPORT) Periodic reporting loop started')
            _server.send_config()
            _server.send_bridge()

        logger.info('(REPORT) HBlink TCP reporting server configured')

        report_server = _factory(_config)
        report_server.clients = []
        reactor.listenTCP(_config['REPORTS']['REPORT_PORT'], report_server)

        reporting = task.LoopingCall(reporting_loop, logger, report_server)
        reporting.start(_config['REPORTS']['REPORT_INTERVAL'])

    return report_server


def make_bridges(_rules):
    '''Original make_bridges kept for compatibility; delegates to _make_bridges_safe.'''
    return _make_bridges_safe(_rules)


def rule_timer_loop():
    global UNIT_MAP
    logger.debug('(ROUTER) routerHBP Rule timer loop started')
    _now = time()

    for _bridge in BRIDGES:
        for _system in BRIDGES[_bridge]:
            if _system['TO_TYPE'] == 'ON':
                if _system['ACTIVE']:
                    if _system['TIMER'] < _now:
                        _system['ACTIVE'] = False
                        logger.info('(ROUTER) Conference Bridge TIMEOUT: DEACTIVATE System: %s, Bridge: %s, TS: %s, TGID: %s', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']))
                    else:
                        timeout_in = _system['TIMER'] - _now
                        logger.info('(ROUTER) Conference Bridge ACTIVE (ON timer running): System: %s Bridge: %s, TS: %s, TGID: %s, Timeout in: %.2fs,', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']), timeout_in)
                elif not _system['ACTIVE']:
                    logger.debug('(ROUTER) Conference Bridge INACTIVE (no change): System: %s Bridge: %s, TS: %s, TGID: %s', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']))
            elif _system['TO_TYPE'] == 'OFF':
                if not _system['ACTIVE']:
                    if _system['TIMER'] < _now:
                        _system['ACTIVE'] = True
                        logger.info('(ROUTER) Conference Bridge TIMEOUT: ACTIVATE System: %s, Bridge: %s, TS: %s, TGID: %s', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']))
                    else:
                        timeout_in = _system['TIMER'] - _now
                        logger.info('(ROUTER) Conference Bridge INACTIVE (OFF timer running): System: %s Bridge: %s, TS: %s, TGID: %s, Timeout in: %.2fs,', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']), timeout_in)
                elif _system['ACTIVE']:
                    logger.debug('(ROUTER) Conference Bridge ACTIVE (no change): System: %s Bridge: %s, TS: %s, TGID: %s', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']))
            else:
                logger.debug('(ROUTER) Conference Bridge NO ACTION: System: %s, Bridge: %s, TS: %s, TGID: %s', _system['SYSTEM'], _bridge, _system['TS'], int_id(_system['TGID']))

    _then = _now - 60
    remove_list = []
    for unit in UNIT_MAP:
        if UNIT_MAP[unit][1] < (_then):
            remove_list.append(unit)

    for unit in remove_list:
        del UNIT_MAP[unit]

    logger.debug('Removed unit(s) %s from UNIT_MAP', remove_list)

    if CONFIG['REPORTS']['REPORT']:
        report_server.send_clients(b'bridge updated')


def stream_trimmer_loop():
    logger.debug('(ROUTER) Trimming inactive stream IDs from system lists')
    _now = time()

    for system in systems:
        if CONFIG['SYSTEMS'][system]['MODE'] != 'OPENBRIDGE':
            for slot in range(1,3):
                _slot  = systems[system].STATUS[slot]

                if _slot['RX_TYPE'] != HBPF_SLT_VTERM and _slot['RX_TIME'] <  _now - 5:
                    _slot['RX_TYPE'] = HBPF_SLT_VTERM
                    logger.info('(%s) *TIME OUT*  RX STREAM ID: %s SUB: %s TGID %s, TS %s, Duration: %.2f', \
                        system, int_id(_slot['RX_STREAM_ID']), int_id(_slot['RX_RFS']), int_id(_slot['RX_TGID']), slot, _slot['RX_TIME'] - _slot['RX_START'])
                    if CONFIG['REPORTS']['REPORT']:
                        systems[system]._report.send_bridgeEvent('GROUP VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(system, int_id(_slot['RX_STREAM_ID']), int_id(_slot['RX_PEER']), int_id(_slot['RX_RFS']), slot, int_id(_slot['RX_TGID']), _slot['RX_TIME'] - _slot['RX_START']).encode(encoding='utf-8', errors='ignore'))

                if _slot['TX_TYPE'] != HBPF_SLT_VTERM and _slot['TX_TIME'] <  _now - 5:
                    _slot['TX_TYPE'] = HBPF_SLT_VTERM
                    logger.info('(%s) *TIME OUT*  TX STREAM ID: %s SUB: %s TGID %s, TS %s, Duration: %.2f', \
                        system, int_id(_slot['TX_STREAM_ID']), int_id(_slot['TX_RFS']), int_id(_slot['TX_TGID']), slot, _slot['TX_TIME'] - _slot['TX_START'])
                    if CONFIG['REPORTS']['REPORT']:
                        systems[system]._report.send_bridgeEvent('GROUP VOICE,END,TX,{},{},{},{},{},{},{:.2f}'.format(system, int_id(_slot['TX_STREAM_ID']), int_id(_slot['TX_PEER']), int_id(_slot['TX_RFS']), slot, int_id(_slot['TX_TGID']), _slot['TX_TIME'] - _slot['TX_START']).encode(encoding='utf-8', errors='ignore'))

        if CONFIG['SYSTEMS'][system]['MODE'] == 'OPENBRIDGE':
            remove_list = []
            for stream_id in systems[system].STATUS:
                if systems[system].STATUS[stream_id]['LAST'] < _now - 5:
                    remove_list.append(stream_id)
            for stream_id in remove_list:
                if stream_id in systems[system].STATUS:
                    _stream = systems[system].STATUS[stream_id]
                    _sysconfig = CONFIG['SYSTEMS'][system]
                    if systems[system].STATUS[stream_id]['ACTIVE']:
                        logger.info('(%s) *TIME OUT*   STREAM ID: %s SUB: %s PEER: %s TYPE: %s DST ID: %s TS 1 Duration: %.2f', \
                        system, int_id(stream_id), get_alias(int_id(_stream['RFS']), subscriber_ids), get_alias(int_id(_sysconfig['NETWORK_ID']), peer_ids), _stream['TYPE'], get_alias(int_id(_stream['DST']), talkgroup_ids), _stream['LAST'] - _stream['START'])
                    if CONFIG['REPORTS']['REPORT']:
                            if _stream['TYPE'] == 'GROUP':
                                systems[system]._report.send_bridgeEvent('GROUP VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(system, int_id(stream_id), int_id(_sysconfig['NETWORK_ID']), int_id(_stream['RFS']), 1, int_id(_stream['DST']), _stream['LAST'] - _stream['START']).encode(encoding='utf-8', errors='ignore'))
                            elif _stream['TYPE'] == 'UNIT':
                                systems[system]._report.send_bridgeEvent('UNIT VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(system, int_id(stream_id), int_id(_sysconfig['NETWORK_ID']), int_id(_stream['RFS']), 1, int_id(_stream['DST']), _stream['LAST'] - _stream['START']).encode(encoding='utf-8', errors='ignore'))
                    removed = systems[system].STATUS.pop(stream_id)
                else:
                    logger.error('(%s) Attemped to remove OpenBridge Stream ID %s not in the Stream ID list: %s', system, int_id(stream_id), [id for id in systems[system].STATUS])

class routerOBP(OPENBRIDGE):

    def __init__(self, _name, _config, _report):
        OPENBRIDGE.__init__(self, _name, _config, _report)
        self.name = _name
        self.STATUS = {}
        self._targets = []

    def group_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data):
        pkt_time = time()
        dmrpkt = _data[20:53]
        _bits = _data[15]

        if (_stream_id not in self.STATUS):
            self.STATUS[_stream_id] = {
                'START':     pkt_time,
                'CONTENTION':False,
                'RFS':       _rf_src,
                'TYPE':      'GROUP',
                'DST':       _dst_id,
                'ACTIVE':    True
            }

            if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                decoded = decode.voice_head_term(dmrpkt)
                self.STATUS[_stream_id]['LC'] = decoded['LC']
            else:
                self.STATUS[_stream_id]['LC'] = LC_OPT + _dst_id + _rf_src

            logger.info('(%s) *GROUP CALL START* OBP STREAM ID: %s SUB: %s (%s) PEER: %s (%s) TGID %s (%s), TS %s', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot)
            if CONFIG['REPORTS']['REPORT']:
                self._report.send_bridgeEvent('GROUP VOICE,START,RX,{},{},{},{},{},{}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

        self.STATUS[_stream_id]['LAST'] = pkt_time

        for _bridge in BRIDGES:
            for _system in BRIDGES[_bridge]:

                if (_system['SYSTEM'] == self._system and _system['TGID'] == _dst_id and _system['TS'] == _slot and _system['ACTIVE'] == True):

                    for _target in BRIDGES[_bridge]:
                        if (_target['SYSTEM'] != self._system) and (_target['ACTIVE']):
                            _target_status = systems[_target['SYSTEM']].STATUS
                            _target_system = self._CONFIG['SYSTEMS'][_target['SYSTEM']]
                            if _target_system['MODE'] == 'OPENBRIDGE':
                                if (_stream_id not in _target_status):
                                    _target_status[_stream_id] = {
                                        'START':     pkt_time,
                                        'CONTENTION':False,
                                        'RFS':       _rf_src,
                                        'TYPE':      'GROUP',
                                        'DST':       _dst_id,
                                        'ACTIVE':    True
                                    }
                                    dst_lc = b''.join([self.STATUS[_stream_id]['LC'][0:3], _target['TGID'], _rf_src])
                                    _target_status[_stream_id]['H_LC'] = bptc.encode_header_lc(dst_lc)
                                    _target_status[_stream_id]['T_LC'] = bptc.encode_terminator_lc(dst_lc)
                                    _target_status[_stream_id]['EMB_LC'] = bptc.encode_emblc(dst_lc)

                                    logger.info('(%s) Conference Bridge: %s, Call Bridged to OBP System: %s TS: %s, TGID: %s', self._system, _bridge, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                    if CONFIG['REPORTS']['REPORT']:
                                        systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,START,TX,{},{},{},{},{},{}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID'])).encode(encoding='utf-8', errors='ignore'))

                                _target_status[_stream_id]['LAST'] = pkt_time
                                _tmp_bits = _bits & ~(1 << 7)
                                _tmp_data = b''.join([_data[:8], _target['TGID'], _data[11:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])

                                dmrbits = bitarray(endian='big')
                                dmrbits.frombytes(dmrpkt)
                                if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                                    dmrbits = _target_status[_stream_id]['H_LC'][0:98] + dmrbits[98:166] + _target_status[_stream_id]['H_LC'][98:197]
                                elif _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VTERM:
                                    dmrbits = _target_status[_stream_id]['T_LC'][0:98] + dmrbits[98:166] + _target_status[_stream_id]['T_LC'][98:197]
                                    if CONFIG['REPORTS']['REPORT']:
                                        call_duration = pkt_time - _target_status[_stream_id]['START']
                                        _target_status[_stream_id]['ACTIVE'] = False
                                        systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,END,TX,{},{},{},{},{},{},{:.2f}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID']), call_duration).encode(encoding='utf-8', errors='ignore'))
                                elif _dtype_vseq in [1,2,3,4]:
                                    dmrbits = dmrbits[0:116] + _target_status[_stream_id]['EMB_LC'][_dtype_vseq] + dmrbits[148:264]
                                dmrpkt = dmrbits.tobytes()
                                _tmp_data = b''.join([_tmp_data, dmrpkt])

                            else:
                                if ((_target['TGID'] != _target_status[_target['TS']]['RX_TGID']) and ((pkt_time - _target_status[_target['TS']]['RX_TIME']) < _target_system['GROUP_HANGTIME'])):
                                    if not self.STATUS[_stream_id]['CONTENTION']:
                                        self.STATUS[_stream_id]['CONTENTION'] = True
                                        logger.info('(%s) Call not routed to TGID %s, target active or in group hangtime: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['RX_TGID']))
                                    continue
                                if ((_target['TGID'] != _target_status[_target['TS']]['TX_TGID']) and ((pkt_time - _target_status[_target['TS']]['TX_TIME']) < _target_system['GROUP_HANGTIME'])):
                                    if not self.STATUS[_stream_id]['CONTENTION']:
                                        self.STATUS[_stream_id]['CONTENTION'] = True
                                        logger.info('(%s) Call not routed to TGID%s, target in group hangtime: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['TX_TGID']))
                                    continue
                                if (_target['TGID'] == _target_status[_target['TS']]['RX_TGID']) and ((pkt_time - _target_status[_target['TS']]['RX_TIME']) < STREAM_TO):
                                    if not self.STATUS[_stream_id]['CONTENTION']:
                                        self.STATUS[_stream_id]['CONTENTION'] = True
                                        logger.info('(%s) Call not routed to TGID%s, matching call already active on target: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['RX_TGID']))
                                    continue
                                if (_target['TGID'] == _target_status[_target['TS']]['TX_TGID']) and (_rf_src != _target_status[_target['TS']]['TX_RFS']) and ((pkt_time - _target_status[_target['TS']]['TX_TIME']) < STREAM_TO):
                                    if not self.STATUS[_stream_id]['CONTENTION']:
                                        self.STATUS[_stream_id]['CONTENTION'] = True
                                        logger.info('(%s) Call not routed for subscriber %s, call route in progress on target: HBSystem: %s, TS: %s, TGID: %s, SUB: %s', self._system, int_id(_rf_src), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['TX_TGID']), int_id(_target_status[_target['TS']]['TX_RFS']))
                                    continue

                                if (_target_status[_target['TS']]['TX_STREAM_ID'] != _stream_id):
                                    _target_status[_target['TS']]['TX_START'] = pkt_time
                                    _target_status[_target['TS']]['TX_TGID'] = _target['TGID']
                                    _target_status[_target['TS']]['TX_STREAM_ID'] = _stream_id
                                    _target_status[_target['TS']]['TX_RFS'] = _rf_src
                                    _target_status[_target['TS']]['TX_PEER'] = _peer_id
                                    dst_lc = b''.join([self.STATUS[_stream_id]['LC'][0:3], _target['TGID'], _rf_src])
                                    _target_status[_target['TS']]['TX_H_LC'] = bptc.encode_header_lc(dst_lc)
                                    _target_status[_target['TS']]['TX_T_LC'] = bptc.encode_terminator_lc(dst_lc)
                                    _target_status[_target['TS']]['TX_EMB_LC'] = bptc.encode_emblc(dst_lc)
                                    logger.debug('(%s) Generating TX FULL and EMB LCs for HomeBrew destination: System: %s, TS: %s, TGID: %s', self._system, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                    logger.info('(%s) Conference Bridge: %s, Call Bridged to HBP System: %s TS: %s, TGID: %s', self._system, _bridge, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                    if CONFIG['REPORTS']['REPORT']:
                                       systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,START,TX,{},{},{},{},{},{}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID'])).encode(encoding='utf-8', errors='ignore'))

                                _target_status[_target['TS']]['TX_TIME'] = pkt_time
                                _target_status[_target['TS']]['TX_TYPE'] = _dtype_vseq

                                if _system['TS'] != _target['TS']:
                                    _tmp_bits = _bits ^ 1 << 7
                                else:
                                    _tmp_bits = _bits

                                _tmp_data = b''.join([_data[:8], _target['TGID'], _data[11:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])

                                dmrbits = bitarray(endian='big')
                                dmrbits.frombytes(dmrpkt)
                                if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                                    dmrbits = _target_status[_target['TS']]['TX_H_LC'][0:98] + dmrbits[98:166] + _target_status[_target['TS']]['TX_H_LC'][98:197]
                                elif _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VTERM:
                                    dmrbits = _target_status[_target['TS']]['TX_T_LC'][0:98] + dmrbits[98:166] + _target_status[_target['TS']]['TX_T_LC'][98:197]
                                    if CONFIG['REPORTS']['REPORT']:
                                        call_duration = pkt_time - _target_status[_target['TS']]['TX_START']
                                        systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,END,TX,{},{},{},{},{},{},{:.2f}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID']), call_duration).encode(encoding='utf-8', errors='ignore'))
                                elif _dtype_vseq in [1,2,3,4]:
                                    dmrbits = dmrbits[0:116] + _target_status[_target['TS']]['TX_EMB_LC'][_dtype_vseq] + dmrbits[148:264]
                                dmrpkt = dmrbits.tobytes()
                                _tmp_data = b''.join([_tmp_data, dmrpkt, b'\x00\x00'])

                            systems[_target['SYSTEM']].send_system(_tmp_data)

        if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM):
            call_duration = pkt_time - self.STATUS[_stream_id]['START']
            logger.info('(%s) *GROUP CALL END*   STREAM ID: %s SUB: %s (%s) PEER: %s (%s) TGID %s (%s), TS %s, Duration: %.2f', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, call_duration)
            if CONFIG['REPORTS']['REPORT']:
               self._report.send_bridgeEvent('GROUP VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), call_duration).encode(encoding='utf-8', errors='ignore'))
            self.STATUS[_stream_id]['ACTIVE'] = False
            logger.debug('(%s) OpenBridge sourced call stream end, remove terminated Stream ID: %s', self._system, int_id(_stream_id))


    def unit_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data):
        global UNIT_MAP
        pkt_time = time()
        dmrpkt = _data[20:53]
        _bits = _data[15]

        UNIT_MAP[_rf_src] = (self.name, pkt_time)

        if (_stream_id not in self.STATUS):
            self.STATUS[_stream_id] = {
                'START':     pkt_time,
                'CONTENTION':False,
                'RFS':       _rf_src,
                'TYPE':      'UNIT',
                'DST':       _dst_id,
                'ACTIVE':    True
            }

            if _dst_id in UNIT_MAP:
                if UNIT_MAP[_dst_id][0] != self._system:
                    self._targets = [UNIT_MAP[_dst_id][0]]
                else:
                    self._targets = []
                    logger.error('UNIT call to a subscriber on the same system, send nothing')
            else:
                self._targets = list(UNIT)
                self._targets.remove(self._system)

            logger.info('(%s) *UNIT CALL START* STREAM ID: %s SUB: %s (%s) PEER: %s (%s) UNIT: %s (%s), TS: %s, FORWARD: %s', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, self._targets)
            if CONFIG['REPORTS']['REPORT']:
                self._report.send_bridgeEvent('UNIT VOICE,START,RX,{},{},{},{},{},{},{}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), self._targets).encode(encoding='utf-8', errors='ignore'))

        self.STATUS[_stream_id]['LAST'] = pkt_time

        for _target in self._targets:
            _target_status = systems[_target].STATUS
            _target_system = self._CONFIG['SYSTEMS'][_target]

            if self._CONFIG['SYSTEMS'][_target]['MODE'] == 'OPENBRIDGE':
                if (_stream_id not in _target_status):
                    _target_status[_stream_id] = {
                        'START':     pkt_time,
                        'CONTENTION':False,
                        'RFS':       _rf_src,
                        'TYPE':      'UNIT',
                        'DST':      _dst_id,
                        'ACTIVE':   True
                    }
                    logger.info('(%s) Unit call bridged to OBP System: %s TS: %s, TGID: %s', self._system, _target, _slot if _target_system['BOTH_SLOTS'] else 1, int_id(_dst_id))
                    if CONFIG['REPORTS']['REPORT']:
                        systems[_target]._report.send_bridgeEvent('UNIT VOICE,START,TX,{},{},{},{},{},{}'.format(_target, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

                _target_status[_stream_id]['LAST'] = pkt_time
                if _target_system['BOTH_SLOTS']:
                    _tmp_bits = _bits
                else:
                    _tmp_bits = _bits & ~(1 << 7)

                _tmp_data = b''.join([_data[:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])
                _data = b''.join([_tmp_data, dmrpkt])

                if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM):
                    _target_status[_stream_id]['ACTIVE'] = False

            else:
                if (_dst_id == _target_status[_slot]['RX_TGID']) and ((pkt_time - _target_status[_slot]['RX_TIME']) < STREAM_TO):
                    if not self.STATUS[_stream_id]['CONTENTION']:
                        self.STATUS[_stream_id]['CONTENTION'] = True
                        logger.info('(%s) Call not routed to TGID%s, matching call already active on target: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_dst_id), _target, _slot, int_id(_target_status[_slot]['RX_TGID']))
                    continue
                if (_dst_id == _target_status[_slot]['TX_TGID']) and (_rf_src != _target_status[_slot]['TX_RFS']) and ((pkt_time - _target_status[_slot]['TX_TIME']) < STREAM_TO):
                    if not self.STATUS[_stream_id]['CONTENTION']:
                        self.STATUS[_stream_id]['CONTENTION'] = True
                        logger.info('(%s) Call not routed for subscriber %s, call route in progress on target: HBSystem: %s, TS: %s, DEST: %s, SUB: %s', self._system, int_id(_rf_src), _target, _slot, int_id(_target_status[_slot]['TX_TGID']), int_id(_target_status[_slot]['TX_RFS']))
                    continue

                if (_stream_id not in self.STATUS):
                    _target_status[_slot]['TX_START'] = pkt_time
                    _target_status[_slot]['TX_TGID'] = _dst_id
                    _target_status[_slot]['TX_STREAM_ID'] = _stream_id
                    _target_status[_slot]['TX_RFS'] = _rf_src
                    _target_status[_slot]['TX_PEER'] = _peer_id
                    logger.info('(%s) Unit call bridged to HBP System: %s TS: %s, UNIT: %s', self._system, _target, _slot, int_id(_dst_id))
                    if CONFIG['REPORTS']['REPORT']:
                       systems[_target]._report.send_bridgeEvent('UNIT VOICE,START,TX,{},{},{},{},{},{}'.format(_target, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

                _target_status[_slot]['TX_TIME'] = pkt_time
                _target_status[_slot]['TX_TYPE'] = _dtype_vseq

            systems[_target].send_system(_data)

            if _target_system['MODE'] == 'OPENBRIDGE':
                if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM):
                    if (_stream_id in _target_status):
                        _target_status.pop(_stream_id)

        if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM):
            self._targets = []
            call_duration = pkt_time - self.STATUS[_stream_id]['START']
            logger.info('(%s) *UNIT CALL END*   STREAM ID: %s SUB: %s (%s) PEER: %s (%s) UNIT %s (%s), TS %s, Duration: %.2f', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, call_duration)
            if CONFIG['REPORTS']['REPORT']:
               self._report.send_bridgeEvent('UNIT VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), call_duration).encode(encoding='utf-8', errors='ignore'))


    def dmrd_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _call_type, _frame_type, _dtype_vseq, _stream_id, _data):
        if _call_type == 'group':
            self.group_received(_peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data)
        elif _call_type == 'unit':
            self.unit_received(_peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data)
        elif _call_type == 'vscsbk':
            logger.debug('CSBK recieved, but HBlink does not process them currently')
        else:
            logger.error('Unknown call type recieved -- not processed')


class routerHBP(HBSYSTEM):

    def __init__(self, _name, _config, _report):
        HBSYSTEM.__init__(self, _name, _config, _report)
        self.name = _name
        self._targets = []

        self.STATUS = {
            1: {
                'RX_START':     time(),
                'TX_START':     time(),
                'RX_SEQ':       0,
                'RX_RFS':       b'\x00',
                'TX_RFS':       b'\x00',
                'RX_PEER':      b'\x00',
                'TX_PEER':      b'\x00',
                'RX_STREAM_ID': b'\x00',
                'TX_STREAM_ID': b'\x00',
                'RX_TGID':      b'\x00\x00\x00',
                'TX_TGID':      b'\x00\x00\x00',
                'RX_TIME':      time(),
                'TX_TIME':      time(),
                'RX_TYPE':      HBPF_SLT_VTERM,
                'TX_TYPE':      HBPF_SLT_VTERM,
                'RX_LC':        b'\x00',
                'TX_H_LC':      b'\x00',
                'TX_T_LC':      b'\x00',
                'TX_EMB_LC': {
                    1: b'\x00',
                    2: b'\x00',
                    3: b'\x00',
                    4: b'\x00',
                    }
                },
            2: {
                'RX_START':     time(),
                'TX_START':     time(),
                'RX_SEQ':       0,
                'RX_RFS':       b'\x00',
                'TX_RFS':       b'\x00',
                'RX_PEER':      b'\x00',
                'TX_PEER':      b'\x00',
                'RX_STREAM_ID': b'\x00',
                'TX_STREAM_ID': b'\x00',
                'RX_TGID':      b'\x00\x00\x00',
                'TX_TGID':      b'\x00\x00\x00',
                'RX_TIME':      time(),
                'TX_TIME':      time(),
                'RX_TYPE':      HBPF_SLT_VTERM,
                'TX_TYPE':      HBPF_SLT_VTERM,
                'RX_LC':        b'\x00',
                'TX_H_LC':      b'\x00',
                'TX_T_LC':      b'\x00',
                'TX_EMB_LC': {
                    1: b'\x00',
                    2: b'\x00',
                    3: b'\x00',
                    4: b'\x00',
                    }
                }
            }


    def group_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data):
        global UNIT_MAP
        pkt_time = time()
        dmrpkt = _data[20:53]
        _bits = _data[15]

        UNIT_MAP[_rf_src] = (self.name, pkt_time)

        if (_stream_id != self.STATUS[_slot]['RX_STREAM_ID']):
            if (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM) and (pkt_time < (self.STATUS[_slot]['RX_TIME'] + STREAM_TO)) and (_rf_src != self.STATUS[_slot]['RX_RFS']):
                logger.warning('(%s) Packet received with STREAM ID: %s <FROM> SUB: %s PEER: %s <TO> TGID %s, SLOT %s collided with existing call', self._system, int_id(_stream_id), int_id(_rf_src), int_id(_peer_id), int_id(_dst_id), _slot)
                return

            self.STATUS[_slot]['RX_START'] = pkt_time
            logger.info('(%s) *GROUP CALL START* STREAM ID: %s SUB: %s (%s) PEER: %s (%s) TGID %s (%s), TS %s', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot)
            if CONFIG['REPORTS']['REPORT']:
                self._report.send_bridgeEvent('GROUP VOICE,START,RX,{},{},{},{},{},{}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

            if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                decoded = decode.voice_head_term(dmrpkt)
                self.STATUS[_slot]['RX_LC'] = decoded['LC']
            else:
                self.STATUS[_slot]['RX_LC'] = LC_OPT + _dst_id + _rf_src

        for _bridge in BRIDGES:
            for _system in BRIDGES[_bridge]:

                if (_system['SYSTEM'] == self._system and _system['TGID'] == _dst_id and _system['TS'] == _slot and _system['ACTIVE'] == True):

                    for _target in BRIDGES[_bridge]:
                        if _target['SYSTEM'] != self._system or (_target['SYSTEM'] == self._system and _target['TS'] != _slot):
                            if _target['ACTIVE']:
                                _target_status = systems[_target['SYSTEM']].STATUS
                                _target_system = self._CONFIG['SYSTEMS'][_target['SYSTEM']]

                                if _target_system['MODE'] == 'OPENBRIDGE':
                                    if (_stream_id not in _target_status):
                                        _target_status[_stream_id] = {
                                            'START':     pkt_time,
                                            'CONTENTION':False,
                                            'RFS':       _rf_src,
                                            'TYPE':     'GROUP',
                                            'DST':      _dst_id,
                                            'ACTIVE':   True,
                                        }
                                        dst_lc = b''.join([self.STATUS[_slot]['RX_LC'][0:3], _target['TGID'], _rf_src])
                                        _target_status[_stream_id]['H_LC'] = bptc.encode_header_lc(dst_lc)
                                        _target_status[_stream_id]['T_LC'] = bptc.encode_terminator_lc(dst_lc)
                                        _target_status[_stream_id]['EMB_LC'] = bptc.encode_emblc(dst_lc)

                                        logger.info('(%s) Conference Bridge: %s, Call Bridged to OBP System: %s TS: %s, TGID: %s', self._system, _bridge, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                        if CONFIG['REPORTS']['REPORT']:
                                            systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,START,TX,{},{},{},{},{},{}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID'])).encode(encoding='utf-8', errors='ignore'))

                                    _target_status[_stream_id]['LAST'] = pkt_time
                                    _tmp_bits = _bits & ~(1 << 7)
                                    _tmp_data = b''.join([_data[:8], _target['TGID'], _data[11:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])

                                    dmrbits = bitarray(endian='big')
                                    dmrbits.frombytes(dmrpkt)
                                    if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                                        dmrbits = _target_status[_stream_id]['H_LC'][0:98] + dmrbits[98:166] + _target_status[_stream_id]['H_LC'][98:197]
                                    elif _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VTERM:
                                        dmrbits = _target_status[_stream_id]['T_LC'][0:98] + dmrbits[98:166] + _target_status[_stream_id]['T_LC'][98:197]
                                        if CONFIG['REPORTS']['REPORT']:
                                            call_duration = pkt_time - _target_status[_stream_id]['START']
                                            _target_status[_stream_id]['ACTIVE'] = False
                                            systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,END,TX,{},{},{},{},{},{},{:.2f}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID']), call_duration).encode(encoding='utf-8', errors='ignore'))
                                    elif _dtype_vseq in [1,2,3,4]:
                                        dmrbits = dmrbits[0:116] + _target_status[_stream_id]['EMB_LC'][_dtype_vseq] + dmrbits[148:264]
                                    dmrpkt = dmrbits.tobytes()
                                    _tmp_data = b''.join([_tmp_data, dmrpkt])

                                else:
                                    if ((_target['TGID'] != _target_status[_target['TS']]['RX_TGID']) and ((pkt_time - _target_status[_target['TS']]['RX_TIME']) < _target_system['GROUP_HANGTIME'])):
                                        if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                                            logger.info('(%s) Call not routed to TGID %s, target active or in group hangtime: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['RX_TGID']))
                                        continue
                                    if ((_target['TGID'] != _target_status[_target['TS']]['TX_TGID']) and ((pkt_time - _target_status[_target['TS']]['TX_TIME']) < _target_system['GROUP_HANGTIME'])):
                                        if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                                            logger.info('(%s) Call not routed to TGID%s, target in group hangtime: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['TX_TGID']))
                                        continue
                                    if (_target['TGID'] == _target_status[_target['TS']]['RX_TGID']) and ((pkt_time - _target_status[_target['TS']]['RX_TIME']) < STREAM_TO):
                                        if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                                            logger.info('(%s) Call not routed to TGID%s, matching call already active on target: HBSystem: %s, TS: %s, TGID: %s', self._system, int_id(_target['TGID']), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['RX_TGID']))
                                        continue
                                    if (_target['TGID'] == _target_status[_target['TS']]['TX_TGID']) and (_rf_src != _target_status[_target['TS']]['TX_RFS']) and ((pkt_time - _target_status[_target['TS']]['TX_TIME']) < STREAM_TO):
                                        if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                                            logger.info('(%s) Call not routed for subscriber %s, call route in progress on target: HBSystem: %s, TS: %s, TGID: %s, SUB: %s', self._system, int_id(_rf_src), _target['SYSTEM'], _target['TS'], int_id(_target_status[_target['TS']]['TX_TGID']), int_id(_target_status[_target['TS']]['TX_RFS']))
                                        continue

                                    if (_stream_id != self.STATUS[_slot]['RX_STREAM_ID']):
                                        _target_status[_target['TS']]['TX_START'] = pkt_time
                                        _target_status[_target['TS']]['TX_TGID'] = _target['TGID']
                                        _target_status[_target['TS']]['TX_STREAM_ID'] = _stream_id
                                        _target_status[_target['TS']]['TX_RFS'] = _rf_src
                                        _target_status[_target['TS']]['TX_PEER'] = _peer_id
                                        dst_lc = self.STATUS[_slot]['RX_LC'][0:3] + _target['TGID'] + _rf_src
                                        _target_status[_target['TS']]['TX_H_LC'] = bptc.encode_header_lc(dst_lc)
                                        _target_status[_target['TS']]['TX_T_LC'] = bptc.encode_terminator_lc(dst_lc)
                                        _target_status[_target['TS']]['TX_EMB_LC'] = bptc.encode_emblc(dst_lc)
                                        logger.debug('(%s) Generating TX FULL and EMB LCs for HomeBrew destination: System: %s, TS: %s, TGID: %s', self._system, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                        logger.info('(%s) Conference Bridge: %s, Call Bridged to HBP System: %s TS: %s, TGID: %s', self._system, _bridge, _target['SYSTEM'], _target['TS'], int_id(_target['TGID']))
                                        if CONFIG['REPORTS']['REPORT']:
                                            systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,START,TX,{},{},{},{},{},{}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID'])).encode(encoding='utf-8', errors='ignore'))

                                    _target_status[_target['TS']]['TX_TIME'] = pkt_time
                                    _target_status[_target['TS']]['TX_TYPE'] = _dtype_vseq

                                    if _system['TS'] != _target['TS']:
                                        _tmp_bits = _bits ^ 1 << 7
                                    else:
                                        _tmp_bits = _bits

                                    _tmp_data = b''.join([_data[:8], _target['TGID'], _data[11:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])

                                    dmrbits = bitarray(endian='big')
                                    dmrbits.frombytes(dmrpkt)
                                    if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD:
                                        dmrbits = _target_status[_target['TS']]['TX_H_LC'][0:98] + dmrbits[98:166] + _target_status[_target['TS']]['TX_H_LC'][98:197]
                                    elif _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VTERM:
                                        dmrbits = _target_status[_target['TS']]['TX_T_LC'][0:98] + dmrbits[98:166] + _target_status[_target['TS']]['TX_T_LC'][98:197]
                                        if CONFIG['REPORTS']['REPORT']:
                                            call_duration = pkt_time - _target_status[_target['TS']]['TX_START']
                                            systems[_target['SYSTEM']]._report.send_bridgeEvent('GROUP VOICE,END,TX,{},{},{},{},{},{},{:.2f}'.format(_target['SYSTEM'], int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _target['TS'], int_id(_target['TGID']), call_duration).encode(encoding='utf-8', errors='ignore'))
                                    elif _dtype_vseq in [1,2,3,4]:
                                        dmrbits = dmrbits[0:116] + _target_status[_target['TS']]['TX_EMB_LC'][_dtype_vseq] + dmrbits[148:264]
                                    dmrpkt = dmrbits.tobytes()
                                    _tmp_data = b''.join([_tmp_data, dmrpkt, _data[53:55]])

                                systems[_target['SYSTEM']].send_system(_tmp_data)

                                if _target_system['MODE'] == 'OPENBRIDGE':
                                    if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM) and (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM):
                                        if (_stream_id in _target_status):
                                            _target_status.pop(_stream_id)


        if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM) and (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM):
            call_duration = pkt_time - self.STATUS[_slot]['RX_START']
            logger.info('(%s) *GROUP CALL END*   STREAM ID: %s SUB: %s (%s) PEER: %s (%s) TGID %s (%s), TS %s, Duration: %.2f', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, call_duration)
            if CONFIG['REPORTS']['REPORT']:
               self._report.send_bridgeEvent('GROUP VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), call_duration).encode(encoding='utf-8', errors='ignore'))

            for _bridge in BRIDGES:
                for _system in BRIDGES[_bridge]:
                    if _system['SYSTEM'] == self._system:
                        if _slot == _system['TS'] and _dst_id == _system['TGID'] and ((_system['TO_TYPE'] == 'ON' and _system['ACTIVE']) or (_system['TO_TYPE'] == 'OFF' and not _system['ACTIVE'])):
                            _system['TIMER'] = pkt_time + _system['TIMEOUT']
                            logger.info('(%s) Transmission match for Bridge: %s. Reset timeout to %s', self._system, _bridge, _system['TIMER'])

                        if (_dst_id in _system['ON'] or _dst_id in _system['RESET']) and _slot == _system['TS']:
                            if _dst_id in _system['ON']:
                                if not _system['ACTIVE']:
                                    _system['ACTIVE'] = True
                                    _system['TIMER'] = pkt_time + _system['TIMEOUT']
                                    logger.info('(%s) Bridge: %s, connection changed to state: %s', self._system, _bridge, _system['ACTIVE'])
                                    if _system['TO_TYPE'] == 'OFF':
                                        _system['TIMER'] = pkt_time
                                        logger.info('(%s) Bridge: %s set to "OFF" with an on timer rule: timeout timer cancelled', self._system, _bridge)
                            if _system['ACTIVE'] and _system['TO_TYPE'] == 'ON':
                                _system['TIMER'] = pkt_time + _system['TIMEOUT']
                                logger.info('(%s) Bridge: %s, timeout timer reset to: %s', self._system, _bridge, _system['TIMER'] - pkt_time)

                        if (_dst_id in _system['OFF']  or _dst_id in _system['RESET']) and _slot == _system['TS']:
                            if _dst_id in _system['OFF']:
                                if _system['ACTIVE']:
                                    _system['ACTIVE'] = False
                                    logger.info('(%s) Bridge: %s, connection changed to state: %s', self._system, _bridge, _system['ACTIVE'])
                                    if _system['TO_TYPE'] == 'ON':
                                        _system['TIMER'] = pkt_time
                                        logger.info('(%s) Bridge: %s set to ON with and "OFF" timer rule: timeout timer cancelled', self._system, _bridge)
                            if not _system['ACTIVE'] and _system['TO_TYPE'] == 'OFF':
                                _system['TIMER'] = pkt_time + _system['TIMEOUT']
                                logger.info('(%s) Bridge: %s, timeout timer reset to: %s', self._system, _bridge, _system['TIMER'] - pkt_time)

        self.STATUS[_slot]['RX_PEER']      = _peer_id
        self.STATUS[_slot]['RX_SEQ']       = _seq
        self.STATUS[_slot]['RX_RFS']       = _rf_src
        self.STATUS[_slot]['RX_TYPE']      = _dtype_vseq
        self.STATUS[_slot]['RX_TGID']      = _dst_id
        self.STATUS[_slot]['RX_TIME']      = pkt_time
        self.STATUS[_slot]['RX_STREAM_ID'] = _stream_id


    def unit_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data):
        global UNIT_MAP
        pkt_time = time()
        dmrpkt = _data[20:53]
        _bits = _data[15]

        UNIT_MAP[_rf_src] = (self.name, pkt_time)

        if (_stream_id != self.STATUS[_slot]['RX_STREAM_ID']):
            if (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM) and (pkt_time < (self.STATUS[_slot]['RX_TIME'] + STREAM_TO)) and (_rf_src != self.STATUS[_slot]['RX_RFS']):
                logger.warning('(%s) Packet received with STREAM ID: %s <FROM> SUB: %s PEER: %s <TO> UNIT %s, SLOT %s collided with existing call', self._system, int_id(_stream_id), int_id(_rf_src), int_id(_peer_id), int_id(_dst_id), _slot)
                return

            if _dst_id in UNIT_MAP:
                if UNIT_MAP[_dst_id][0] != self._system:
                    self._targets = [UNIT_MAP[_dst_id][0]]
                else:
                    self._targets = []
                    logger.error('UNIT call to a subscriber on the same system, send nothing')
            else:
                self._targets = list(UNIT)
                self._targets.remove(self._system)

            self.STATUS[_slot]['RX_START'] = pkt_time
            logger.info('(%s) *UNIT CALL START* STREAM ID: %s SUB: %s (%s) PEER: %s (%s) UNIT: %s (%s), TS: %s, FORWARD: %s', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, self._targets)
            if CONFIG['REPORTS']['REPORT']:
                self._report.send_bridgeEvent('UNIT VOICE,START,RX,{},{},{},{},{},{},{}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), self._targets).encode(encoding='utf-8', errors='ignore'))

        for _target in self._targets:
            _target_status = systems[_target].STATUS
            _target_system = self._CONFIG['SYSTEMS'][_target]

            if self._CONFIG['SYSTEMS'][_target]['MODE'] == 'OPENBRIDGE':
                if (_stream_id not in _target_status):
                    _target_status[_stream_id] = {
                        'START':     pkt_time,
                        'CONTENTION':False,
                        'RFS':       _rf_src,
                        'TYPE':      'UNIT',
                        'DST':      _dst_id,
                        'ACTIVE':   True
                    }
                    logger.info('(%s) Unit call bridged to OBP System: %s TS: %s, UNIT: %s', self._system, _target, _slot if _target_system['BOTH_SLOTS'] else 1, int_id(_dst_id))
                    if CONFIG['REPORTS']['REPORT']:
                        systems[_target]._report.send_bridgeEvent('UNIT VOICE,START,TX,{},{},{},{},{},{}'.format(_target, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

                _target_status[_stream_id]['LAST'] = pkt_time
                if _target_system['BOTH_SLOTS']:
                    _tmp_bits = _bits
                else:
                    _tmp_bits = _bits & ~(1 << 7)

                _tmp_data = b''.join([_data[:15], _tmp_bits.to_bytes(1, 'big'), _data[16:20]])
                _data = b''.join([_tmp_data, dmrpkt])

                if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM):
                    _target_status[_stream_id]['ACTIVE'] = False

            else:
                if (_dst_id == _target_status[_slot]['RX_TGID']) and ((pkt_time - _target_status[_slot]['RX_TIME']) < STREAM_TO):
                    if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                        logger.info('(%s) Call not routed to destination %s, matching call already active on target: HBSystem: %s, TS: %s, DEST: %s', self._system, int_id(_dst_id), _target, _slot, int_id(_target_status[_slot]['RX_TGID']))
                    continue
                if (_dst_id == _target_status[_slot]['TX_TGID']) and (_rf_src != _target_status[_slot]['TX_RFS']) and ((pkt_time - _target_status[_slot]['TX_TIME']) < STREAM_TO):
                    if _frame_type == HBPF_DATA_SYNC and _dtype_vseq == HBPF_SLT_VHEAD and self.STATUS[_slot]['RX_STREAM_ID'] != _stream_id:
                        logger.info('(%s) Call not routed for subscriber %s, call route in progress on target: HBSystem: %s, TS: %s, DEST: %s, SUB: %s', self._system, int_id(_rf_src), _target, _slot, int_id(_target_status[_slot]['TX_TGID']), int_id(_target_status[_slot]['TX_RFS']))
                    continue

                if (_stream_id != self.STATUS[_slot]['RX_STREAM_ID']):
                    _target_status[_slot]['TX_START'] = pkt_time
                    _target_status[_slot]['TX_TGID'] = _dst_id
                    _target_status[_slot]['TX_STREAM_ID'] = _stream_id
                    _target_status[_slot]['TX_RFS'] = _rf_src
                    _target_status[_slot]['TX_PEER'] = _peer_id
                    logger.info('(%s) Unit call bridged to HBP System: %s TS: %s, UNIT: %s', self._system, _target, _slot, int_id(_dst_id))
                    if CONFIG['REPORTS']['REPORT']:
                       systems[_target]._report.send_bridgeEvent('UNIT VOICE,START,TX,{},{},{},{},{},{}'.format(_target, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id)).encode(encoding='utf-8', errors='ignore'))

                _target_status[_slot]['TX_TIME'] = pkt_time
                _target_status[_slot]['TX_TYPE'] = _dtype_vseq

            systems[_target].send_system(_data)

            if _target_system['MODE'] == 'OPENBRIDGE':
                if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM) and (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM):
                    if (_stream_id in _target_status):
                        _target_status.pop(_stream_id)

        if (_frame_type == HBPF_DATA_SYNC) and (_dtype_vseq == HBPF_SLT_VTERM) and (self.STATUS[_slot]['RX_TYPE'] != HBPF_SLT_VTERM):
            self._targets = []
            call_duration = pkt_time - self.STATUS[_slot]['RX_START']
            logger.info('(%s) *UNIT CALL END*   STREAM ID: %s SUB: %s (%s) PEER: %s (%s) UNIT %s (%s), TS %s, Duration: %.2f', \
                    self._system, int_id(_stream_id), get_alias(_rf_src, subscriber_ids), int_id(_rf_src), get_alias(_peer_id, peer_ids), int_id(_peer_id), get_alias(_dst_id, talkgroup_ids), int_id(_dst_id), _slot, call_duration)
            if CONFIG['REPORTS']['REPORT']:
               self._report.send_bridgeEvent('UNIT VOICE,END,RX,{},{},{},{},{},{},{:.2f}'.format(self._system, int_id(_stream_id), int_id(_peer_id), int_id(_rf_src), _slot, int_id(_dst_id), call_duration).encode(encoding='utf-8', errors='ignore'))

        self.STATUS[_slot]['RX_PEER']      = _peer_id
        self.STATUS[_slot]['RX_SEQ']       = _seq
        self.STATUS[_slot]['RX_RFS']       = _rf_src
        self.STATUS[_slot]['RX_TYPE']      = _dtype_vseq
        self.STATUS[_slot]['RX_TGID']      = _dst_id
        self.STATUS[_slot]['RX_TIME']      = pkt_time
        self.STATUS[_slot]['RX_STREAM_ID'] = _stream_id


    def dmrd_received(self, _peer_id, _rf_src, _dst_id, _seq, _slot, _call_type, _frame_type, _dtype_vseq, _stream_id, _data):
        if _call_type == 'group':
            self.group_received(_peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data)
        elif _call_type == 'unit':
            if self._system not in UNIT:
                logger.error('(%s) *UNIT CALL NOT FORWARDED* UNIT calling is disabled for this system (INGRESS)', self._system)
            else:
                self.unit_received(_peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data)
        elif _call_type == 'vcsbk':
            logger.debug('CSBK recieved, routing to ' + str(int_id(_dst_id)))
            self.group_received(_peer_id, _rf_src, _dst_id, _seq, _slot, _frame_type, _dtype_vseq, _stream_id, _data)
        else:
            logger.error('Unknown call type recieved -- not processed')


class bridgeReportFactory(reportFactory):

    def send_bridge(self):
        serialized = pickle.dumps(BRIDGES, protocol=2)
        self.send_clients(REPORT_OPCODES['BRIDGE_SND']+serialized)

    def send_bridgeEvent(self, _data):
        if isinstance(_data, str):
            _data = _data.decode('utf-8', error='ignore')
        self.send_clients(REPORT_OPCODES['BRDG_EVENT']+_data)


#************************************************
#      MAIN PROGRAM LOOP STARTS HERE
#************************************************

if __name__ == '__main__':

    import argparse
    import sys
    import os
    import signal

    os.chdir(os.path.dirname(os.path.realpath(sys.argv[0])))

    parser = argparse.ArgumentParser()
    parser.add_argument('-c', '--config', action='store', dest='CONFIG_FILE', help='/full/path/to/config.file (usually hblink.cfg)')
    parser.add_argument('-l', '--logging', action='store', dest='LOG_LEVEL', help='Override config file logging level.')
    cli_args = parser.parse_args()

    if not cli_args.CONFIG_FILE:
        cli_args.CONFIG_FILE = os.path.dirname(os.path.abspath(__file__))+'/hblink.cfg'

    CONFIG = config.build_config(cli_args.CONFIG_FILE)

    if cli_args.LOG_LEVEL:
        CONFIG['LOGGER']['LOG_LEVEL'] = cli_args.LOG_LEVEL
    logger = log.config_logging(CONFIG['LOGGER'])
    logger.info('\n\nCopyright (c) 2013, 2014, 2015, 2016, 2018, 2019, 2020\n\tThe Regents of the K0USY Group. All rights reserved.\n')
    logger.debug('(GLOBAL) Logging system started, anything from here on gets logged')

    def sig_handler(_signal, _frame):
        logger.info('(GLOBAL) SHUTDOWN: CONFBRIDGE IS TERMINATING WITH SIGNAL %s', str(_signal))
        hblink_handler(_signal, _frame)
        logger.info('(GLOBAL) SHUTDOWN: ALL SYSTEM HANDLERS EXECUTED - STOPPING REACTOR')
        reactor.stop()

    for sig in [signal.SIGINT, signal.SIGTERM]:
        signal.signal(sig, sig_handler)

    peer_ids, subscriber_ids, talkgroup_ids = mk_aliases(CONFIG)

    # -----------------------------------------------------------------------
    # PATCHED: load BRIDGES from bridge_routes.json instead of rules.py
    # -----------------------------------------------------------------------
    logger.info('(ROUTER) Loading initial bridge routes from %s', BRIDGE_ROUTES_JSON)
    _load_bridge_routes()
    if not BRIDGES:
        logger.warning('(ROUTER) bridge_routes.json not yet available or empty — starting with no routes. Will reload automatically.')

    # UNIT: systems that receive forwarded private calls when target is unknown.
    # Default to all MASTER-mode systems defined in config.
    UNIT = [s for s in CONFIG['SYSTEMS'] if CONFIG['SYSTEMS'][s].get('MODE') == 'MASTER']
    logger.info('(ROUTER) UNIT call targets: %s', UNIT)
    # -----------------------------------------------------------------------

    if CONFIG['REPORTS']['REPORT']:
        report_server = config_reports(CONFIG, bridgeReportFactory)
    else:
        report_server = None
        logger.info('(REPORT) TCP Socket reporting not configured')

    logger.info('(GLOBAL) HBlink \'bridge.py\' (patched: DB-driven routing) -- SYSTEM STARTING...')
    for system in CONFIG['SYSTEMS']:
        if CONFIG['SYSTEMS'][system]['ENABLED']:
            if CONFIG['SYSTEMS'][system]['MODE'] == 'OPENBRIDGE':
                systems[system] = routerOBP(system, CONFIG, report_server)
            else:
                systems[system] = routerHBP(system, CONFIG, report_server)
            reactor.listenUDP(CONFIG['SYSTEMS'][system]['PORT'], systems[system], interface=CONFIG['SYSTEMS'][system]['IP'])
            logger.debug('(GLOBAL) %s instance created: %s, %s', CONFIG['SYSTEMS'][system]['MODE'], system, systems[system])

    def loopingErrHandle(failure):
        logger.error('(GLOBAL) STOPPING REACTOR TO AVOID MEMORY LEAK: Unhandled error in timed loop.\n %s', failure)
        reactor.stop()

    rule_timer_task = task.LoopingCall(rule_timer_loop)
    rule_timer = rule_timer_task.start(60)
    rule_timer.addErrback(loopingErrHandle)

    stream_trimmer_task = task.LoopingCall(stream_trimmer_loop)
    stream_trimmer = stream_trimmer_task.start(5)
    stream_trimmer.addErrback(loopingErrHandle)

    # Poll bridge_routes.json every 10 seconds for routing changes
    routes_reload_task = task.LoopingCall(routes_reload_loop)
    routes_reload = routes_reload_task.start(10)
    routes_reload.addErrback(loopingErrHandle)

    reactor.run()
