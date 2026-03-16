const { create } = require('fake-sso-idp')
const app = create({
  serviceProvider: {
    destination: 'http://127.0.0.1/saml2/acs',
    metadata: 'http://127.0.0.1/saml2/local/metadata'
  },
  users: [
    {
      id: 'test1',
      name: 'SuperAdmin Enrico Oliva',
      username: 'enrico',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'super-admin',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Enrico Oliva',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'LVONRC76C29L500F',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'enrico.oliva@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13': {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'ND',
          type: 'xs:string'
        }
      }
    },
    {
      id: 'test2',
      name: 'Operatore Uff. Docente Paolo',
      username: 'enrico',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'op_approvazione_amm',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Paolo Mencaccini',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'MNCPLA78P29B352U',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'paolo.mencaccini@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13': {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'ND',
          type: 'xs:string'
        }
      }
    },
    {
      id: 'test3',
      name: 'Operatore Uff. Economico Fiorella',
      username: 'enrico',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: ' op_approvazione_economica',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Firorella Perugini',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'PRGFLL61P56L500P',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'fiorella.perugini@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13': {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'ND',
          type: 'xs:string'
        }
      }
    },
    {
      id: 'test4',
      name: 'Operatore Dipartimentale Laura',
      username: 'enrico',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: ' op_dipartimentale',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Laura Feduzi',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'FDZLRA72S53L500W',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'laura.feduzi@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13': {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'ND',
          type: 'xs:string'
        }
      }
    },
    {
      id: 'test5',
      name: 'Docente Luigina',
      username: 'enrico',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: ' op_docente',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Luigina',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: '',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'luigia.sabatini@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13' :{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'SD',
          type: 'xs:string'
        },
      }
    },
    {
      id: 'test6',
      name: 'Viewer Manola DiLuca',
      username: 'manola',
      password: 'pwd',
      attributes: {
        pisa_id: {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: ' viewer',
          type: 'xs:string'
        },
       'urn:oid:2.16.840.1.113730.3.1.241':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'Manola DiLuca',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.4203.666.11.11.1.0':{
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: '',
          type: 'xs:string'
        },
        'urn:oid:0.9.2342.19200300.100.1.3':{
          format:  'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'manola.diluca@uniurb.it',
          type: 'xs:string'
        },
        'urn:oid:1.3.6.1.4.1.27280.1.13': {
          format: 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
          value: 'ND',
          type: 'xs:string'
        }
      }
    }
  ]
})

app.listen(7000)